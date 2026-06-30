<?php
/**
 * POST endpoint: start a subscription.
 * Creates a pending subscription, opens the first checkout and redirects the
 * customer to the gateway's card page.
 */

declare(strict_types=1);

require_once __DIR__ . '/SubscriptionService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$plan  = $_POST['plan']  ?? '';
$name  = $_POST['name']  ?? '';
$email = $_POST['email'] ?? '';

try {
    $service = new SubscriptionService();
    $result = $service->startSubscription($plan, $name, $email);
    header('Location: ' . $result['redirect_url']);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif">შეცდომა: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="/subscribe.php">უკან დაბრუნება</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif">გადახდის დაწყება ვერ მოხერხდა. სცადეთ მოგვიანებით.</p>';
    error_log('subscribe error: ' . $e->getMessage());
}
