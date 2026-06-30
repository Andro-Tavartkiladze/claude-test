<?php
/**
 * Provider-neutral payment gateway contract.
 *
 * Any provider (Bank of Georgia, TBC, Stripe, a mock simulator …) implements
 * this interface, so the business logic in SubscriptionService never depends on
 * a specific bank. Switching providers means writing one new class — nothing
 * else changes.
 */

declare(strict_types=1);

interface PaymentGateway
{
    /**
     * Start the FIRST payment. This payment also registers the card so that
     * future months can be charged automatically (card tokenisation happens on
     * the provider's PCI-compliant page — we never see the card number).
     *
     * @param array $order {
     *   external_order_id: string,  our reference
     *   amount_minor:      int,     amount in tetri
     *   currency:          string,  e.g. 'GEL'
     *   description:       string,
     *   callback_url:      string,  server-to-server status webhook
     *   success_url:       string,  browser redirect after success
     *   fail_url:          string,  browser redirect after failure
     *   customer_email:    string,
     * }
     * @return array{order_id:string, redirect_url:string}
     */
    public function createInitialCheckout(array $order): array;

    /**
     * Charge a previously saved card automatically (no customer present).
     * Referenced against the parent order created by createInitialCheckout().
     *
     * @return array{order_id:string, status:string, detail:string}
     *         status is 'success' or 'failed'.
     */
    public function chargeRecurring(string $parentOrderId, array $order): array;

    /**
     * Look up the authoritative status of an order from the provider. Used to
     * verify a callback before acting on it (never trust callback body alone).
     *
     * @return array{status:string, card_mask:?string, order_id:string}
     *         status is 'success' | 'failed' | 'pending'.
     */
    public function getOrderStatus(string $orderId): array;
}
