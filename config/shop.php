<?php

declare(strict_types=1);

return [
    'billing' => [
        'first_name' => env('SHOP_BILLING_NAME'),
        'email' => env('SHOP_BILLING_EMAIL'),
        'phone' => env('SHOP_BILLING_PHONE'),
        'address_line1' => env('SHOP_BILLING_LINE1'),
        'city' => env('SHOP_BILLING_CITY'),
        'state' => env('SHOP_BILLING_STATE'),
        'postal_code' => env('SHOP_BILLING_ZIP'),
        'country' => env('SHOP_BILLING_COUNTRY'),
    ],
];
