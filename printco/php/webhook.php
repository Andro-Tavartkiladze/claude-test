<?php
/**
 * Server-to-server callback endpoint.
 *
 * The gateway calls this URL when a payment's status changes. We:
 *   1. verify the request is authentic (RSA signature for BOG, HMAC for mock);
 *   2. re-confirm the authoritative status from the gateway (never trust the
 *      body alone) when possible;
 *   3. update the payment + activate the subscription idempotently.
 *
 * Always returns 200 quickly so the gateway does not keep retrying once we have
 * accepted the notification.
 */

declare(strict_types=1);

require_once __DIR__ . '/SubscriptionService.php';

$config = require __DIR__ . '/config.php';
$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true) ?? $_POST;

if (!verify_callback($config, $raw, $payload)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid signature']);
    exit;
}

$providerOrderId = (string) (
    $payload['order_id']
    ?? ($payload['body']['order_id'] ?? ($payload['external_order_id'] ?? ''))
);
$reportedStatus = strtolower((string) (
    $payload['status']
    ?? ($payload['body']['order_status']['key'] ?? ($payload['order_status'] ?? ''))
));
$cardMask = $payload['card_mask'] ?? ($payload['body']['payment_detail']['card_mask'] ?? null);

if ($providerOrderId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing order id']);
    exit;
}

$service = new SubscriptionService(null, $config);

// Trust-but-verify: for the real bank, re-read the status from the API.
$status = $reportedStatus;
if ($config['gateway'] === 'bog') {
    try {
        $auth = $service->getGatewayOrderStatus($providerOrderId);
        $status = $auth['status'];
        $cardMask = $auth['card_mask'] ?? $cardMask;
    } catch (Throwable $e) {
        error_log('webhook status re-check failed: ' . $e->getMessage());
    }
}

$normalized = in_array($status, ['success', 'completed', 'captured', 'approved'], true) ? 'success' : $status;
$service->confirmPayment($providerOrderId, $normalized === 'success' ? 'success' : 'failed', $cardMask);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);

/* --------------------------------------------------------------------- */

function verify_callback(array $config, string $raw, array $payload): bool
{
    if ($config['gateway'] === 'mock') {
        // Mock signs with HMAC-SHA256 over the order id using a shared secret.
        $given = $_SERVER['HTTP_X_MOCK_SIGNATURE'] ?? ($payload['signature'] ?? '');
        $orderId = (string) ($payload['order_id'] ?? '');
        $expected = hash_hmac('sha256', $orderId, $config['mock_secret']);
        return hash_equals($expected, (string) $given);
    }

    // BOG: verify the RSA signature sent in the Callback-Signature header.
    $publicKey = $config['bog']['public_key'] ?? '';
    $signature = $_SERVER['HTTP_CALLBACK_SIGNATURE'] ?? '';
    if ($publicKey === '' || $signature === '') {
        error_log('BOG callback missing public key or signature header');
        return false;
    }
    $ok = openssl_verify($raw, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);
    return $ok === 1;
}
