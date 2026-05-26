<?php

declare(strict_types=1);

return [
    'enabled' => env('AVATAX_ENABLED', false),
    'environment' => env('AVATAX_ENVIRONMENT', 'sandbox'),
    'account' => env('AVATAX_ACCOUNT_NUMBER'),
    'license_key' => env('AVATAX_LICENSE_KEY'),
    'company_code' => env('AVATAX_COMPANY_CODE'),
    'company_id' => env('AVALARA_COMPANY_ID'),
    'ship_from' => [
        'street' => env('AVATAX_SHIP_FROM_STREET'),
        'city' => env('AVATAX_SHIP_FROM_CITY'),
        'state' => env('AVATAX_SHIP_FROM_STATE'),
        'zip' => env('AVATAX_SHIP_FROM_ZIP'),
        'country' => env('AVATAX_SHIP_FROM_COUNTRY', 'US'),
    ],
];
