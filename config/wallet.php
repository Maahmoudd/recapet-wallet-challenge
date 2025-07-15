<?php

return [

    'calculation' => [
        'min_transfer_amount' => env('MINIMUM_TRANSFER_AMOUNT', 25.00),
        'base_fee' => env('BASE_FEE', 2.50),
        'percentage_fee' => env('PERCENTAGE_FEE', 0.10),
    ],

];
