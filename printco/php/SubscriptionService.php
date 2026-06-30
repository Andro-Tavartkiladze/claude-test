<?php
/**
 * SubscriptionService — the recurring-billing engine.
 *
 * Responsibilities:
 *   - create a subscription and start the first (card-saving) checkout
 *   - activate it once the first payment is confirmed (via webhook)
 *   - charge all DUE subscriptions automatically (called daily by cron)
 *   - dunning: retry failures, then mark past_due
 *   - cancel a subscription
 *
 * All money is handled in minor units (tetri, int). Dates are 'Y-m-d' strings.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gateway/factory.php';

final class SubscriptionService
{
    private PDO $db;
    private PaymentGateway $gateway;
    private array $config;

    public function __construct(?PaymentGateway $gateway = null, ?array $config = null)
    {
        $this->config  = $config ?? require __DIR__ . '/config.php';
        $this->db      = db();
        $this->gateway = $gateway ?? make_gateway($this->config);
    }

    /* ---- public API ---------------------------------------------------- */

    /**
     * Create a pending subscription and start the first checkout.
     *
     * @return array{subscription_uid:string, redirect_url:string}
     */
    public function startSubscription(string $planCode, string $name, string $email): array
    {
        $plan = $this->planByCode($planCode);
        if ($plan === null) {
            throw new InvalidArgumentException('უცნობი გეგმა: ' . $planCode);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('არასწორი ელ.ფოსტა');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('სახელი სავალდებულოა');
        }

        $now = $this->now();
        $uid = 'sub_' . bin2hex(random_bytes(10));

        $stmt = $this->db->prepare(<<<SQL
            INSERT INTO subscriptions
                (uid, plan_id, customer_name, customer_email, status, created_at, updated_at)
            VALUES (:uid, :plan_id, :name, :email, 'pending_payment', :now, :now)
        SQL);
        $stmt->execute([
            ':uid' => $uid, ':plan_id' => $plan['id'], ':name' => $name,
            ':email' => $email, ':now' => $now,
        ]);
        $subscriptionId = (int) $this->db->lastInsertId();

        $externalOrderId = $uid . '-init';
        $order = $this->buildOrder($externalOrderId, $plan, $email, 'PrintCo გამოწერა — ' . $plan['name']);

        $checkout = $this->gateway->createInitialCheckout($order);

        $this->recordPayment(
            $subscriptionId, $externalOrderId, $checkout['order_id'],
            (int) $plan['amount_minor'], $plan['currency'], 'initial', 'pending', 1, null
        );

        return ['subscription_uid' => $uid, 'redirect_url' => $checkout['redirect_url']];
    }

    /**
     * Confirm a payment reported by a (verified) callback or status check, and
     * activate the subscription on the first successful one. Idempotent.
     */
    public function confirmPayment(string $providerOrderId, string $status, ?string $cardMask = null): void
    {
        $payment = $this->db->prepare('SELECT * FROM payments WHERE provider_order_id = :oid LIMIT 1');
        $payment->execute([':oid' => $providerOrderId]);
        $payment = $payment->fetch();
        if ($payment === false) {
            return; // unknown order — nothing to do
        }
        if ($payment['status'] !== 'pending') {
            return; // already processed (idempotent)
        }

        $sub = $this->subscriptionById((int) $payment['subscription_id']);
        if ($sub === null) {
            return;
        }

        $newStatus = $status === 'success' ? 'success' : 'failed';
        $this->db->prepare('UPDATE payments SET status = :s, detail = :d WHERE id = :id')
            ->execute([':s' => $newStatus, ':d' => 'confirmed via callback', ':id' => $payment['id']]);

        if ($newStatus !== 'success') {
            return;
        }

        if ($payment['type'] === 'initial') {
            // Activate: the saved card / parent order is this initial order id.
            $plan = $this->planById((int) $sub['plan_id']);
            $today = $this->today();
            $next  = $this->addMonths($today, (int) $plan['interval_months']);

            $this->db->prepare(<<<SQL
                UPDATE subscriptions SET
                    status = 'active',
                    parent_order_id = :poid,
                    card_mask = :mask,
                    current_period_start = :start,
                    next_charge_date = :next,
                    failed_attempts = 0,
                    updated_at = :now
                WHERE id = :id
            SQL)->execute([
                ':poid' => $providerOrderId,
                ':mask' => $cardMask,
                ':start' => $today,
                ':next' => $next,
                ':now' => $this->now(),
                ':id' => $sub['id'],
            ]);
        }
    }

    /**
     * Charge every subscription whose next_charge_date is due on/before $today.
     * Called daily by cron/charge_due.php. Safe to run repeatedly.
     *
     * @return array{charged:int, failed:int, past_due:int, processed:int}
     */
    public function chargeDueSubscriptions(?string $today = null): array
    {
        $today = $today ?? $this->today();
        $stmt = $this->db->prepare(<<<SQL
            SELECT * FROM subscriptions
            WHERE status = 'active' AND next_charge_date IS NOT NULL AND next_charge_date <= :today
            ORDER BY next_charge_date ASC
        SQL);
        $stmt->execute([':today' => $today]);
        $due = $stmt->fetchAll();

        $summary = ['charged' => 0, 'failed' => 0, 'past_due' => 0, 'processed' => 0];

        foreach ($due as $sub) {
            $summary['processed']++;
            $result = $this->chargeOne($sub, $today);
            $summary[$result]++;
        }

        return $summary;
    }

    /** Authoritative status from the gateway (used by the webhook to verify). */
    public function getGatewayOrderStatus(string $providerOrderId): array
    {
        return $this->gateway->getOrderStatus($providerOrderId);
    }

    public function cancel(string $uid): bool
    {
        $stmt = $this->db->prepare(<<<SQL
            UPDATE subscriptions
            SET status = 'canceled', canceled_at = :now, next_charge_date = NULL, updated_at = :now
            WHERE uid = :uid AND status != 'canceled'
        SQL);
        $stmt->execute([':now' => $this->now(), ':uid' => $uid]);
        return $stmt->rowCount() > 0;
    }

    /* ---- internals ----------------------------------------------------- */

    /** @return 'charged'|'failed'|'past_due' */
    private function chargeOne(array $sub, string $today): string
    {
        $plan = $this->planById((int) $sub['plan_id']);
        $attempt = (int) $sub['failed_attempts'] + 1;
        $externalOrderId = sprintf('%s-r%s-a%d', $sub['uid'], str_replace('-', '', $today), $attempt);

        $order = $this->buildOrder(
            $externalOrderId, $plan, $sub['customer_email'],
            'PrintCo გამოწერა — ' . $plan['name']
        );

        try {
            $result = $this->gateway->chargeRecurring((string) $sub['parent_order_id'], $order);
        } catch (Throwable $e) {
            $result = ['order_id' => $externalOrderId, 'status' => 'failed', 'detail' => 'exception: ' . $e->getMessage()];
        }

        $success = $result['status'] === 'success';
        $this->recordPayment(
            (int) $sub['id'], $externalOrderId, $result['order_id'],
            (int) $plan['amount_minor'], $plan['currency'], 'recurring',
            $success ? 'success' : 'failed', $attempt, $result['detail'] ?? null
        );

        if ($success) {
            $next = $this->addMonths($today, (int) $plan['interval_months']);
            $this->db->prepare(<<<SQL
                UPDATE subscriptions SET
                    current_period_start = :start, next_charge_date = :next,
                    failed_attempts = 0, updated_at = :now
                WHERE id = :id
            SQL)->execute([':start' => $today, ':next' => $next, ':now' => $this->now(), ':id' => $sub['id']]);
            return 'charged';
        }

        // Dunning: retry tomorrow until max_retries, then suspend.
        if ($attempt >= (int) $this->config['max_retries']) {
            $this->db->prepare(<<<SQL
                UPDATE subscriptions SET
                    status = 'past_due', failed_attempts = :att,
                    next_charge_date = NULL, updated_at = :now
                WHERE id = :id
            SQL)->execute([':att' => $attempt, ':now' => $this->now(), ':id' => $sub['id']]);
            return 'past_due';
        }

        $retryDate = $this->addDays($today, 1);
        $this->db->prepare(<<<SQL
            UPDATE subscriptions SET
                failed_attempts = :att, next_charge_date = :retry, updated_at = :now
            WHERE id = :id
        SQL)->execute([':att' => $attempt, ':retry' => $retryDate, ':now' => $this->now(), ':id' => $sub['id']]);
        return 'failed';
    }

    private function buildOrder(string $externalOrderId, array $plan, string $email, string $description): array
    {
        $base = $this->config['base_url'];
        return [
            'external_order_id' => $externalOrderId,
            'amount_minor'      => (int) $plan['amount_minor'],
            'currency'          => $plan['currency'],
            'description'       => $description,
            'callback_url'      => $base . '/php/webhook.php',
            'success_url'       => $base . '/php/return.php?status=success',
            'fail_url'          => $base . '/php/return.php?status=fail',
            'customer_email'    => $email,
        ];
    }

    private function recordPayment(
        int $subId, string $externalOrderId, string $providerOrderId,
        int $amountMinor, string $currency, string $type, string $status,
        int $attempt, ?string $detail
    ): void {
        $this->db->prepare(<<<SQL
            INSERT INTO payments
                (subscription_id, external_order_id, provider_order_id, amount_minor,
                 currency, type, status, attempt, detail, created_at)
            VALUES (:sid, :ext, :prov, :amt, :cur, :type, :status, :attempt, :detail, :now)
        SQL)->execute([
            ':sid' => $subId, ':ext' => $externalOrderId, ':prov' => $providerOrderId,
            ':amt' => $amountMinor, ':cur' => $currency, ':type' => $type,
            ':status' => $status, ':attempt' => $attempt, ':detail' => $detail,
            ':now' => $this->now(),
        ]);
    }

    /* ---- small queries / helpers -------------------------------------- */

    public function plans(): array
    {
        return $this->db->query('SELECT * FROM plans WHERE active = 1 ORDER BY amount_minor ASC')->fetchAll();
    }

    public function planByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE code = :c AND active = 1');
        $stmt->execute([':c' => $code]);
        return $stmt->fetch() ?: null;
    }

    public function planById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM plans WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function subscriptionByUid(string $uid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE uid = :u');
        $stmt->execute([':u' => $uid]);
        return $stmt->fetch() ?: null;
    }

    public function subscriptionById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function paymentsForSubscription(int $subId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE subscription_id = :id ORDER BY id DESC');
        $stmt->execute([':id' => $subId]);
        return $stmt->fetchAll();
    }

    public function allSubscriptions(): array
    {
        return $this->db->query(<<<SQL
            SELECT s.*, p.name AS plan_name, p.amount_minor, p.currency
            FROM subscriptions s JOIN plans p ON p.id = s.plan_id
            ORDER BY s.id DESC
        SQL)->fetchAll();
    }

    private function now(): string  { return date('Y-m-d H:i:s'); }
    private function today(): string { return date('Y-m-d'); }

    private function addMonths(string $date, int $months): string
    {
        return (new DateTimeImmutable($date))->modify("+$months month")->format('Y-m-d');
    }

    private function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify("+$days day")->format('Y-m-d');
    }

    public static function formatAmount(int $minor, string $currency = 'GEL'): string
    {
        $symbol = $currency === 'GEL' ? '₾' : $currency;
        return number_format($minor / 100, 2) . ' ' . $symbol;
    }
}
