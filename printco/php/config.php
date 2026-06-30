<?php
/**
 * Centralised configuration for the subscription / recurring-billing system.
 *
 * Values are read from environment variables so that nothing secret lives in
 * the repository. A local `.env` file (see .env.example) is loaded for
 * convenience during development. In production set these as real env vars.
 */

declare(strict_types=1);

/* ---- minimal .env loader (development convenience) ---------------------- */
(function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
})();

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

return [
    /*
     * Active payment gateway.
     *   'mock' -> fully working simulator, no credentials needed (default)
     *   'bog'  -> real Bank of Georgia (iPay) API
     */
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    // Absolute base URL of this site, used to build callback / redirect URLs.
    'base_url' => rtrim(env('APP_BASE_URL', 'http://localhost:8080') ?? '', '/'),

    // SQLite database file (created automatically on first run).
    'db_path' => env('DB_PATH', dirname(__DIR__) . '/data/payments.sqlite'),

    // How many failed automatic charges before a subscription is marked past_due.
    'max_retries' => (int) env('DUNNING_MAX_RETRIES', '3'),

    // Shared secret used to authenticate the mock gateway's callback. The real
    // BOG callback is verified with the bank's RSA public key instead.
    'mock_secret' => env('MOCK_CALLBACK_SECRET', 'dev-mock-secret'),

    /* ---- Bank of Georgia (iPay) credentials ---------------------------- */
    'bog' => [
        'client_id'     => env('BOG_CLIENT_ID', ''),
        'client_secret' => env('BOG_CLIENT_SECRET', ''),
        // BOG sends a `Callback-Signature` header signed with this RSA public key.
        'public_key'    => env('BOG_PUBLIC_KEY', ''),
        'oauth_url'     => env('BOG_OAUTH_URL', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'),
        'api_base'      => env('BOG_API_BASE', 'https://api.bog.ge/payments/v1'),
    ],
];
