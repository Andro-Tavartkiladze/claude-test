<?php
/**
 * Minimal admin view of subscriptions and their payment history.
 *
 * Protected with HTTP Basic auth. Set ADMIN_USER / ADMIN_PASS in the
 * environment; if unset it falls back to admin / admin for local development.
 */

declare(strict_types=1);

require_once __DIR__ . '/../php/SubscriptionService.php';

$user = getenv('ADMIN_USER') ?: 'admin';
$pass = getenv('ADMIN_PASS') ?: 'admin';
if (($_SERVER['PHP_AUTH_USER'] ?? '') !== $user || ($_SERVER['PHP_AUTH_PW'] ?? '') !== $pass) {
    header('WWW-Authenticate: Basic realm="PrintCo Admin"');
    http_response_code(401);
    exit('Authentication required');
}

$service = new SubscriptionService();
$subs = $service->allSubscriptions();

$badge = static function (string $status): string {
    $colors = [
        'active' => '#1b9e4b', 'pending_payment' => '#b08900',
        'past_due' => '#c0392b', 'canceled' => '#777',
    ];
    $c = $colors[$status] ?? '#555';
    return '<span style="background:' . $c . ';color:#fff;padding:2px 8px;border-radius:6px;font-size:.8rem;">'
        . htmlspecialchars($status) . '</span>';
};

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <title>PrintCo — გამოწერების ადმინი</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; margin: 30px; color: #1f1f2e; }
    h1 { font-size: 1.4rem; }
    table { border-collapse: collapse; width: 100%; margin-top: 16px; font-size: .92rem; }
    th, td { border: 1px solid #e0e0ec; padding: 8px 10px; text-align: left; }
    th { background: #f3f3fa; }
    tr:nth-child(even) { background: #fafaff; }
    .muted { color: #888; }
  </style>
</head>
<body>
  <h1>გამოწერები (<?= count($subs) ?>)</h1>
  <p class="muted">Gateway: <strong><?= htmlspecialchars((require __DIR__ . '/../php/config.php')['gateway']) ?></strong></p>
  <table>
    <thead>
      <tr>
        <th>#</th><th>გეგმა</th><th>კლიენტი</th><th>სტატუსი</th>
        <th>თანხა</th><th>შემდეგი ჩამოჭრა</th><th>წარუმ. ცდები</th><th>ბარათი</th><th>გადახდები</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subs as $s): ?>
        <?php $pays = $service->paymentsForSubscription((int) $s['id']); ?>
        <tr>
          <td><?= (int) $s['id'] ?></td>
          <td><?= htmlspecialchars($s['plan_name']) ?></td>
          <td><?= htmlspecialchars($s['customer_name']) ?><br><span class="muted"><?= htmlspecialchars($s['customer_email']) ?></span></td>
          <td><?= $badge($s['status']) ?></td>
          <td><?= htmlspecialchars(SubscriptionService::formatAmount((int) $s['amount_minor'], $s['currency'])) ?></td>
          <td><?= htmlspecialchars($s['next_charge_date'] ?? '—') ?></td>
          <td><?= (int) $s['failed_attempts'] ?></td>
          <td><?= htmlspecialchars($s['card_mask'] ?? '—') ?></td>
          <td>
            <?php foreach ($pays as $p): ?>
              <div class="muted">
                <?= htmlspecialchars($p['type']) ?> ·
                <?= htmlspecialchars($p['status']) ?> ·
                <?= htmlspecialchars(SubscriptionService::formatAmount((int) $p['amount_minor'], $p['currency'])) ?> ·
                <?= htmlspecialchars($p['created_at']) ?>
              </div>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
