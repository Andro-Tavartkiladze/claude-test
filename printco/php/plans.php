<?php
/**
 * Subscription plans for PrintCo.
 *
 * Amounts are stored in MINOR units (tetri) as integers to avoid floating
 * point rounding errors. 1 GEL = 100 tetri.
 *
 * These rows are seeded into the `plans` table on first run (see db.php).
 */

declare(strict_types=1);

return [
    [
        'code'            => 'starter',
        'name'            => 'Starter — მცირე ბიზნესი',
        'amount_minor'    => 2900,   // 29.00 ₾ / თვე
        'interval_months' => 1,
        'description'     => 'ყოველთვიურად 100 ბიზნეს ბარათი + 50 ფლაერი.',
    ],
    [
        'code'            => 'business',
        'name'            => 'Business — მზარდი ბიზნესი',
        'amount_minor'    => 7900,   // 79.00 ₾ / თვე
        'interval_months' => 1,
        'description'     => '300 ბარათი, 200 ფლაერი და 20 ბროშურა ყოველთვიურად.',
    ],
    [
        'code'            => 'pro',
        'name'            => 'Pro — სააგენტო',
        'amount_minor'    => 19900,  // 199.00 ₾ / თვე
        'interval_months' => 1,
        'description'     => 'ულიმიტო კალკულატორი, პრიორიტეტული ბეჭდვა და ბანერები.',
    ],
];
