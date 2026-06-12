<?php
// Base prices per unit (in GEL tetri) and tier discounts for printing products
return [
    'business_cards' => [
        'label' => 'ბიზნეს ბარათები',
        'base_price' => 0.35,
        'options' => [
            'paper' => [
                'standard' => 0,
                'matte'    => 0.05,
                'glossy'   => 0.08,
                'premium'  => 0.15,
            ],
            'sides' => [
                'single' => 0,
                'double' => 0.06,
            ],
        ],
        'min_qty' => 50,
    ],
    'flyers' => [
        'label' => 'ფლაერები',
        'base_price' => 0.25,
        'options' => [
            'paper' => [
                'standard' => 0,
                'matte'    => 0.04,
                'glossy'   => 0.07,
                'premium'  => 0.12,
            ],
            'sides' => [
                'single' => 0,
                'double' => 0.05,
            ],
        ],
        'min_qty' => 100,
    ],
    'brochures' => [
        'label' => 'ბროშურები (A4, ტრიფოლდი)',
        'base_price' => 0.9,
        'options' => [
            'paper' => [
                'standard' => 0,
                'matte'    => 0.1,
                'glossy'   => 0.18,
                'premium'  => 0.3,
            ],
            'sides' => [
                'single' => 0,
                'double' => 0.1,
            ],
        ],
        'min_qty' => 50,
    ],
    'banners' => [
        'label' => 'ბანერები (კვ.მ)',
        'base_price' => 18,
        'options' => [
            'paper' => [
                'standard' => 0,
                'matte'    => 2,
                'glossy'   => 3,
                'premium'  => 6,
            ],
            'sides' => [
                'single' => 0,
                'double' => 12,
            ],
        ],
        'min_qty' => 1,
    ],
];
