<?php
/**
 * SQLite connection + schema migration + plan seeding.
 *
 * Returns a configured PDO instance. The schema is created the first time the
 * file is opened, so there is no separate migration step to run.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $path = $config['db_path'];

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrate($pdo);
    seedPlans($pdo);

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS plans (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            code            TEXT    NOT NULL UNIQUE,
            name            TEXT    NOT NULL,
            amount_minor    INTEGER NOT NULL,
            currency        TEXT    NOT NULL DEFAULT 'GEL',
            interval_months INTEGER NOT NULL DEFAULT 1,
            description     TEXT    NOT NULL DEFAULT '',
            active          INTEGER NOT NULL DEFAULT 1
        );
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS subscriptions (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            uid                  TEXT    NOT NULL UNIQUE,
            plan_id              INTEGER NOT NULL REFERENCES plans(id),
            customer_name        TEXT    NOT NULL,
            customer_email       TEXT    NOT NULL,
            status               TEXT    NOT NULL DEFAULT 'pending_payment',
            parent_order_id      TEXT,
            card_mask            TEXT,
            current_period_start TEXT,
            next_charge_date     TEXT,
            failed_attempts      INTEGER NOT NULL DEFAULT 0,
            created_at           TEXT    NOT NULL,
            updated_at           TEXT    NOT NULL,
            canceled_at          TEXT
        );
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS payments (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            subscription_id   INTEGER NOT NULL REFERENCES subscriptions(id),
            external_order_id TEXT    NOT NULL,
            provider_order_id TEXT,
            amount_minor      INTEGER NOT NULL,
            currency          TEXT    NOT NULL DEFAULT 'GEL',
            type              TEXT    NOT NULL,           -- initial | recurring
            status            TEXT    NOT NULL DEFAULT 'pending', -- pending | success | failed
            attempt           INTEGER NOT NULL DEFAULT 1,
            detail            TEXT,
            created_at        TEXT    NOT NULL
        );
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sub_due ON subscriptions(status, next_charge_date)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pay_sub ON payments(subscription_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pay_provider ON payments(provider_order_id)');
}

function seedPlans(PDO $pdo): void
{
    $plans = require __DIR__ . '/plans.php';
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO plans (code, name, amount_minor, interval_months, description)
        VALUES (:code, :name, :amount_minor, :interval_months, :description)
        ON CONFLICT(code) DO UPDATE SET
            name            = excluded.name,
            amount_minor    = excluded.amount_minor,
            interval_months = excluded.interval_months,
            description     = excluded.description
    SQL);
    foreach ($plans as $p) {
        $stmt->execute([
            ':code'            => $p['code'],
            ':name'            => $p['name'],
            ':amount_minor'    => $p['amount_minor'],
            ':interval_months' => $p['interval_months'],
            ':description'     => $p['description'],
        ]);
    }
}
