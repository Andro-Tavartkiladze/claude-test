<?php
header('Content-Type: application/json; charset=utf-8');

$products = require __DIR__ . '/products.php';

$productKey = $_POST['product'] ?? '';
$paper      = $_POST['paper'] ?? 'standard';
$sides      = $_POST['sides'] ?? 'single';
$quantity   = (int)($_POST['quantity'] ?? 0);

if (!isset($products[$productKey])) {
    echo json_encode(['error' => 'არასწორი პროდუქტი']);
    exit;
}

$product = $products[$productKey];

if ($quantity < $product['min_qty']) {
    echo json_encode([
        'error' => sprintf('მინიმალური რაოდენობაა %d', $product['min_qty']),
    ]);
    exit;
}

$paperSurcharge = $product['options']['paper'][$paper] ?? 0;
$sidesSurcharge = $product['options']['sides'][$sides] ?? 0;

$unitPrice = $product['base_price'] + $paperSurcharge + $sidesSurcharge;

// Volume discount tiers
$discount = 0;
if ($quantity >= 1000) {
    $discount = 0.15;
} elseif ($quantity >= 500) {
    $discount = 0.10;
} elseif ($quantity >= 200) {
    $discount = 0.05;
}

$subtotal = $unitPrice * $quantity;
$total = $subtotal * (1 - $discount);

echo json_encode([
    'product'    => $product['label'],
    'unit_price' => round($unitPrice, 2),
    'quantity'   => $quantity,
    'discount'   => $discount * 100,
    'subtotal'   => round($subtotal, 2),
    'total'      => round($total, 2),
]);
