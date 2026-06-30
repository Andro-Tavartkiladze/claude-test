<?php
/**
 * End-to-end test of the recurring-billing engine using the mock gateway.
 * No HTTP server and no bank credentials required.
 *
 * Run:  php printco/tests/test_recurring.php
 *
 * Covers:
 *   1. start subscription -> pending + checkout redirect
 *   2. confirm initial payment -> subscription active, next charge in 1 month
 *   3. charge due next month -> charged, next charge advances
 *   4. dunning: failing card retries then goes past_due
 *   5. cancel stops future charges
 */

declare(strict_types=1);

// Isolated temp DB + mock gateway, set BEFORE anything reads config.
$tmpDb = sys_get_temp_dir() . '/printco_test_' . getmypid() . '.sqlite';
@unlink($tmpDb);
putenv('DB_PATH=' . $tmpDb);
putenv('PAYMENT_GATEWAY=mock');
putenv('APP_BASE_URL=http://localhost:8080');
putenv('DUNNING_MAX_RETRIES=3');

require_once __DIR__ . '/../php/SubscriptionService.php';

$tests = 0;
$failed = 0;
function check(string $label, bool $cond): void
{
    global $tests, $failed;
    $tests++;
    if ($cond) {
        echo "  ✅ $label\n";
    } else {
        $failed++;
        echo "  ❌ $label\n";
    }
}

$service = new SubscriptionService();

/* --- 1. start a subscription -------------------------------------------- */
echo "1) გამოწერის დაწყება\n";
$start = $service->startSubscription('business', 'გიორგი ტესტი', 'giorgi@example.com');
$sub = $service->subscriptionByUid($start['subscription_uid']);
check('subscription created', $sub !== null);
check('status is pending_payment', $sub['status'] === 'pending_payment');
check('redirect url returned', str_contains($start['redirect_url'], 'mock_pay.php'));
$initialPayment = $service->paymentsForSubscription((int) $sub['id'])[0] ?? null;
check('initial payment row pending', $initialPayment && $initialPayment['status'] === 'pending');

/* --- 2. confirm the first payment (simulates the verified webhook) ------- */
echo "2) პირველი გადახდის დადასტურება (webhook)\n";
$service->confirmPayment($initialPayment['provider_order_id'], 'success', '5***-****-****-1234');
$sub = $service->subscriptionByUid($start['subscription_uid']);
check('status is active', $sub['status'] === 'active');
check('card mask stored', $sub['card_mask'] === '5***-****-****-1234');
$expectedNext = (new DateTimeImmutable(date('Y-m-d')))->modify('+1 month')->format('Y-m-d');
check('next charge in 1 month', $sub['next_charge_date'] === $expectedNext);

// idempotency: confirming the same order again changes nothing.
$service->confirmPayment($initialPayment['provider_order_id'], 'success');
check('confirm is idempotent', count($service->paymentsForSubscription((int) $sub['id'])) === 1);

/* --- 3. automatic monthly charge ---------------------------------------- */
echo "3) ავტომატური ყოველთვიური ჩამოჭრა (cron)\n";
$chargeDay = $sub['next_charge_date'];
$summary = $service->chargeDueSubscriptions($chargeDay);
check('one subscription charged', $summary['charged'] === 1);
$sub = $service->subscriptionByUid($start['subscription_uid']);
$expectedNext2 = (new DateTimeImmutable($chargeDay))->modify('+1 month')->format('Y-m-d');
check('next charge advanced 1 month', $sub['next_charge_date'] === $expectedNext2);
$recurring = array_filter($service->paymentsForSubscription((int) $sub['id']), fn ($p) => $p['type'] === 'recurring');
check('recurring payment recorded as success', count($recurring) === 1 && reset($recurring)['status'] === 'success');

// running again the same day must NOT double charge.
$again = $service->chargeDueSubscriptions($chargeDay);
check('no double charge on same day', $again['processed'] === 0);

/* --- 4. dunning: a declining card --------------------------------------- */
echo "4) Dunning — ბარათი უარყოფს (retry -> past_due)\n";
$badStart = $service->startSubscription('starter', 'უარის ბარათი', 'decline-user@example.com');
$badSub = $service->subscriptionByUid($badStart['subscription_uid']);
$badInitial = $service->paymentsForSubscription((int) $badSub['id'])[0];
// First payment confirmed (card saved); the *recurring* charges will decline.
$service->confirmPayment($badInitial['provider_order_id'], 'success', '5***-****-****-9999');
$badSub = $service->subscriptionByUid($badStart['subscription_uid']);

$day = $badSub['next_charge_date'];
$service->chargeDueSubscriptions($day);                 // attempt 1 -> fail, retry +1d
$badSub = $service->subscriptionByUid($badStart['subscription_uid']);
check('after fail #1 still active, 1 attempt', $badSub['status'] === 'active' && (int) $badSub['failed_attempts'] === 1);

$day = $badSub['next_charge_date'];
$service->chargeDueSubscriptions($day);                 // attempt 2 -> fail
$badSub = $service->subscriptionByUid($badStart['subscription_uid']);
check('after fail #2 still active, 2 attempts', $badSub['status'] === 'active' && (int) $badSub['failed_attempts'] === 2);

$day = $badSub['next_charge_date'];
$service->chargeDueSubscriptions($day);                 // attempt 3 -> past_due
$badSub = $service->subscriptionByUid($badStart['subscription_uid']);
check('after fail #3 marked past_due', $badSub['status'] === 'past_due');
check('past_due has no next charge date', $badSub['next_charge_date'] === null);

/* --- 5. cancel ----------------------------------------------------------- */
echo "5) გაუქმება\n";
$ok = $service->cancel($start['subscription_uid']);
check('cancel returns true', $ok === true);
$sub = $service->subscriptionByUid($start['subscription_uid']);
check('status canceled', $sub['status'] === 'canceled');
$noMore = $service->chargeDueSubscriptions($expectedNext2);
check('canceled subscription not charged', $noMore['processed'] === 0);

/* --- summary ------------------------------------------------------------- */
@unlink($tmpDb);
@unlink($tmpDb . '-wal');
@unlink($tmpDb . '-shm');

echo "\n";
echo str_repeat('-', 40) . "\n";
if ($failed === 0) {
    echo "✅ ყველა ტესტი გაიარა ($tests/$tests)\n";
    exit(0);
}
echo "❌ ჩავარდა $failed / $tests\n";
exit(1);
