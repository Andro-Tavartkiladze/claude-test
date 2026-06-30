<?php
/**
 * Cancel a subscription by its public uid. Stops future automatic charges.
 */

declare(strict_types=1);

require_once __DIR__ . '/SubscriptionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$uid = $_POST['uid'] ?? '';
$service = new SubscriptionService();
$done = $service->cancel($uid);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => $done]);
