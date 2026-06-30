<?php
/**
 * Gateway factory — returns the PaymentGateway implementation selected in
 * config. Defaults to the mock simulator when no provider credentials exist.
 */

declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/MockBogGateway.php';
require_once __DIR__ . '/BogGateway.php';

function make_gateway(?array $config = null): PaymentGateway
{
    $config ??= require __DIR__ . '/../config.php';

    return match ($config['gateway']) {
        'bog'   => new BogGateway($config),
        'mock'  => new MockBogGateway($config),
        default => throw new RuntimeException('Unknown gateway: ' . $config['gateway']),
    };
}
