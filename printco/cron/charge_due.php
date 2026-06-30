<?php
/**
 * Daily scheduler — charges every subscription that is due today.
 *
 * Run once per day from cron / a scheduled machine, e.g.:
 *     0 6 * * *  php /srv/http/cron/charge_due.php >> /var/log/printco-billing.log 2>&1
 *
 * Idempotent and safe to run more than once per day: a subscription is only
 * charged when next_charge_date <= today, and that date is advanced on success.
 *
 * Optional arg: a 'Y-m-d' date to simulate "today" (useful for testing).
 */

declare(strict_types=1);

require_once __DIR__ . '/../php/SubscriptionService.php';

$today = $argv[1] ?? date('Y-m-d');

$service = new SubscriptionService();
$summary = $service->chargeDueSubscriptions($today);

printf(
    "[%s] billing run for %s — processed:%d charged:%d failed(retry):%d past_due:%d\n",
    date('Y-m-d H:i:s'),
    $today,
    $summary['processed'],
    $summary['charged'],
    $summary['failed'],
    $summary['past_due']
);
