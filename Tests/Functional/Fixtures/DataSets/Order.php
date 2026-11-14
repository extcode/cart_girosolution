<?php

declare(strict_types=1);

return [
    'tx_cart_domain_model_order_item' => [
        [
            'uid' => 1,
            'pid' => 2,
            'order_number' => 'ORD-2026-0001',
            'currency_code' => 'EUR',
            'total_gross' => 119.0,
            'payment' => 1,
        ],
    ],
    'tx_cart_domain_model_order_payment' => [
        [
            'uid' => 1,
            'pid' => 2,
            'item' => 1,
            'service_id' => 1,
            'name' => 'Credit Card',
            'provider' => 'GIROSOLUTION_CREDITCARD',
            'status' => 'open',
            'transactions' => 1,
        ],
    ],
    'tx_cart_domain_model_order_transaction' => [
        [
            'uid' => 1,
            'pid' => 2,
            'payment' => 1,
            'txn_id' => 'gc-reference-0001',
            'status' => 'created',
        ],
    ],
];
