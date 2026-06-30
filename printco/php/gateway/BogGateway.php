<?php
/**
 * Bank of Georgia (iPay) gateway — real HTTP integration.
 *
 * Flow used for recurring billing:
 *   1. OAuth2 client_credentials -> access token.
 *   2. createInitialCheckout(): create an e-commerce order with the card saved
 *      for future automatic payments. BOG returns an order id + a redirect link
 *      where the customer enters the card.
 *   3. chargeRecurring(): execute an automatic payment against the saved card,
 *      referencing the parent order id.
 *   4. Callbacks are signed by BOG with an RSA key (Callback-Signature header),
 *      verified in webhook.php with the configured public key.
 *
 * NOTE: Endpoint paths and the exact field used to flag "save card for
 * automatic payments" depend on the product enabled on your BOG merchant
 * contract. The constants below reflect BOG's public e-commerce API; confirm
 * them against the documentation you receive with your credentials and adjust
 * SAVE_CARD_SUBSCRIPTION / the automatic-charge path if your contract differs.
 */

declare(strict_types=1);

final class BogGateway implements PaymentGateway
{
    public function __construct(private array $config)
    {
        if (($config['bog']['client_id'] ?? '') === '' || ($config['bog']['client_secret'] ?? '') === '') {
            throw new RuntimeException('BOG credentials are not configured (BOG_CLIENT_ID / BOG_CLIENT_SECRET).');
        }
    }

    public function createInitialCheckout(array $order): array
    {
        $token = $this->accessToken();

        $body = [
            'callback_url'      => $order['callback_url'],
            'external_order_id' => $order['external_order_id'],
            'purchase_units'    => [
                'currency'     => $order['currency'],
                'total_amount' => $this->toMajor($order['amount_minor']),
                'basket'       => [[
                    'product_id' => $order['external_order_id'],
                    'description'=> $order['description'],
                    'quantity'   => 1,
                    'unit_price' => $this->toMajor($order['amount_minor']),
                ]],
            ],
            'redirect_urls'     => [
                'success' => $order['success_url'],
                'fail'    => $order['fail_url'],
            ],
            // Register the card so subsequent months can be charged automatically.
            'payment_method'    => ['card'],
            'config'            => [
                'subscription' => ['save_card' => true],
            ],
        ];

        $res = $this->request('POST', '/ecommerce/orders', $body, $token);

        $orderId = $res['id'] ?? ($res['order_id'] ?? '');
        $redirect = $res['_links']['redirect']['href']
            ?? ($res['links']['redirect'] ?? ($res['redirect_url'] ?? ''));

        if ($orderId === '' || $redirect === '') {
            throw new RuntimeException('BOG: unexpected create-order response: ' . json_encode($res));
        }

        return ['order_id' => (string) $orderId, 'redirect_url' => (string) $redirect];
    }

    public function chargeRecurring(string $parentOrderId, array $order): array
    {
        $token = $this->accessToken();

        $body = [
            'external_order_id' => $order['external_order_id'],
            'purchase_units'    => [
                'currency'     => $order['currency'],
                'total_amount' => $this->toMajor($order['amount_minor']),
            ],
        ];

        // Automatic payment against the saved card of the parent order.
        $res = $this->request('POST', "/ecommerce/orders/{$parentOrderId}/subscribe", $body, $token);

        $orderId = $res['id'] ?? ($res['order_id'] ?? $parentOrderId);
        $status  = $this->normalizeStatus($res['status'] ?? ($res['order_status']['key'] ?? ''));

        return [
            'order_id' => (string) $orderId,
            'status'   => $status === 'success' ? 'success' : 'failed',
            'detail'   => 'bog: ' . json_encode($res['status'] ?? $res),
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $token = $this->accessToken();
        $res = $this->request('GET', "/receipt/{$orderId}", null, $token);

        $status = $this->normalizeStatus(
            $res['order_status']['key'] ?? ($res['status'] ?? 'pending')
        );
        $mask = $res['payment_detail']['card_mask'] ?? ($res['card_mask'] ?? null);

        return ['status' => $status, 'card_mask' => $mask, 'order_id' => $orderId];
    }

    /* ----------------------------------------------------------------- */

    private function accessToken(): string
    {
        static $cached = null;
        static $expires = 0;
        if ($cached !== null && time() < $expires) {
            return $cached;
        }

        $ch = curl_init($this->config['bog']['oauth_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'client_credentials']),
            CURLOPT_USERPWD        => $this->config['bog']['client_id'] . ':' . $this->config['bog']['client_secret'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("BOG OAuth transport error: $err");
        }
        curl_close($ch);

        $data = json_decode((string) $raw, true);
        if ($code !== 200 || !isset($data['access_token'])) {
            throw new RuntimeException("BOG OAuth failed (HTTP $code): " . $raw);
        }

        $cached = (string) $data['access_token'];
        $expires = time() + (int) ($data['expires_in'] ?? 60) - 10;
        return $cached;
    }

    private function request(string $method, string $path, ?array $body, string $token): array
    {
        $url = $this->config['bog']['api_base'] . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: ka',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("BOG transport error on $path: $err");
        }
        curl_close($ch);

        $data = json_decode((string) $raw, true) ?? [];
        if ($code >= 400) {
            throw new RuntimeException("BOG API error on $path (HTTP $code): " . $raw);
        }
        return $data;
    }

    private function normalizeStatus(string $raw): string
    {
        $raw = strtolower($raw);
        return match ($raw) {
            'completed', 'success', 'captured', 'approved', 'refunded' => 'success',
            'rejected', 'failed', 'declined', 'error', 'blocked'       => 'failed',
            default                                                    => 'pending',
        };
    }

    private function toMajor(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
