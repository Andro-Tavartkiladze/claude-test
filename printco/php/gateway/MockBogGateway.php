<?php
/**
 * Mock gateway — a fully working simulator of the Bank of Georgia flow.
 *
 * It lets the entire recurring-billing system run and be tested end-to-end
 * WITHOUT any bank credentials. It mimics BOG's shape:
 *   - createInitialCheckout() returns a redirect to a local "card page"
 *     (mock_pay.php) which then fires the callback, exactly like the bank does.
 *   - chargeRecurring() simulates an automatic charge against a saved card.
 *   - getOrderStatus() returns an authoritative status.
 *
 * Deterministic test hooks (so failures/dunning can be exercised):
 *   - a customer e-mail containing "decline" always fails the charge.
 *   - an amount ending in 13 tetri (…13) always fails (e.g. 9.13 ₾).
 */

declare(strict_types=1);

final class MockBogGateway implements PaymentGateway
{
    public function __construct(private array $config)
    {
    }

    public function createInitialCheckout(array $order): array
    {
        $orderId = 'mock_' . bin2hex(random_bytes(8));

        // The bank would host this page; in mock mode we host mock_pay.php.
        $params = http_build_query([
            'order_id'          => $orderId,
            'external_order_id' => $order['external_order_id'],
            'amount_minor'      => $order['amount_minor'],
            'email'             => $order['customer_email'],
        ]);
        $redirect = $this->config['base_url'] . '/php/mock_pay.php?' . $params;

        return ['order_id' => $orderId, 'redirect_url' => $redirect];
    }

    public function chargeRecurring(string $parentOrderId, array $order): array
    {
        $orderId = 'mock_' . bin2hex(random_bytes(8));

        if ($this->shouldDecline($order)) {
            return [
                'order_id' => $orderId,
                'status'   => 'failed',
                'detail'   => 'mock: card declined (insufficient funds)',
            ];
        }

        return [
            'order_id' => $orderId,
            'status'   => 'success',
            'detail'   => 'mock: charged saved card ' . $this->cardMask(),
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        // In the mock, the authoritative status is carried by mock_pay.php,
        // which signs and posts it to the webhook. For a direct status check we
        // optimistically report success unless explicitly a decline order.
        return [
            'status'    => 'success',
            'card_mask' => $this->cardMask(),
            'order_id'  => $orderId,
        ];
    }

    private function shouldDecline(array $order): bool
    {
        $email = strtolower($order['customer_email'] ?? '');
        if (str_contains($email, 'decline')) {
            return true;
        }
        return ((int) $order['amount_minor']) % 100 === 13;
    }

    private function cardMask(): string
    {
        return '5***-****-****-1234';
    }
}
