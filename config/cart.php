<?php

return [
    'departments' => [
        'shein',
        'computer',
        'store',
        'services',

    ],

    'default_department' => env('CART_DEFAULT_DEPARTMENT', 'store'),


    'department_roots' => [
        'shein' => (int) env('CART_SHEIN_ROOT_CATEGORY_ID', 4),
        'computer' => (int) env('CART_COMPUTER_ROOT_CATEGORY_ID', 5),
        // Default must stay aligned with Constant.storeRootCategoryId (3) in the apps
        // to avoid mis-detecting which listings support ecommerce purchase options.
        'store' => (int) env('CART_STORE_ROOT_CATEGORY_ID', 3),
        'services' => null,
    ],

    'interface_map' => [
        'shein_products' => 'shein',
        'shein' => 'shein',
        'computer_section' => 'computer',
        'computer' => 'computer',
        'store_products' => 'store',
        'store' => 'store',
        'service_requests' => 'services',
        'services' => 'services',

    ],
];
