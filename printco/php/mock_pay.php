<?php
/**
 * Mock card page — stands in for the bank's hosted payment form while running
 * in mock mode. Lets you choose to PAY or DECLINE, then fires the signed
 * callback to webhook.php exactly like the real bank would, and redirects back.
 *
 * This file only does anything when the active gateway is 'mock'.
 */

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
if ($config['gateway'] !== 'mock') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/SubscriptionService.php';

$orderId   = $_REQUEST['order_id'] ?? '';
$amount    = (int) ($_REQUEST['amount_minor'] ?? 0);
$email     = $_REQUEST['email'] ?? '';
$base      = $config['base_url'];

if ($orderId === '') {
    http_response_code(400);
    exit('missing order_id');
}

// On submit: deliver the signed callback to our own webhook, then redirect.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'] ?? 'pay';
    $status = $decision === 'pay' ? 'success' : 'failed';
    $signature = hash_hmac('sha256', $orderId, $config['mock_secret']);

    $body = json_encode([
        'order_id'  => $orderId,
        'status'    => $status,
        'card_mask' => '5***-****-****-1234',
        'signature' => $signature,
    ]);

    $ch = curl_init($base . '/php/webhook.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Mock-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);

    $redirect = $base . '/php/return.php?status=' . ($status === 'success' ? 'success' : 'fail');
    header('Location: ' . $redirect);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$amountText = SubscriptionService::formatAmount($amount);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>გადახდა (MOCK)</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="container" style="max-width:420px;margin:60px auto;">
    <div class="calc-box">
      <h2 class="section-title">სატესტო გადახდა</h2>
      <p style="text-align:center;color:var(--muted)">
        ეს არის <strong>MOCK</strong> გვერდი — ბანკის credentials არ არის საჭირო.<br>
        რეალურ რეჟიმში აქ Bank of Georgia-ს ბარათის ფორმა იქნება.
      </p>
      <p style="text-align:center;font-size:1.4rem;margin:18px 0;">
        თანხა: <strong><?= htmlspecialchars($amountText) ?></strong>
      </p>
      <form method="post">
        <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
        <button type="submit" name="decision" value="pay" class="btn btn-primary" style="width:100%;margin-bottom:10px;background:var(--primary);color:#fff;">გადახდა ✅</button>
        <button type="submit" name="decision" value="decline" class="btn" style="width:100%;background:#eee;color:#333;">უარყოფა ❌</button>
      </form>
    </div>
  </div>
</body>
</html>
