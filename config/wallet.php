<?php

return [

    'calculation' => [
        'min_transfer_amount' => env('MINIMUM_TRANSFER_AMOUNT', 25.00),
        'base_fee' => env('BASE_FEE', 2.50),
        'percentage_fee' => env('PERCENTAGE_FEE', 0.10),
    ],

    'limits' => [
        'max_transaction_amount' => 999999.99,
        'min_transaction_amount' => 0.01,
        'daily_transfer_limit' => 50000.00,
    ],

    'snapshots' => [
        'retention_days' => 365,
        'schedule_time' => '23:59',
    ],

];
