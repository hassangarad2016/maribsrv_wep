<?php

return [
    'currency' => env('WALLET_CURRENCY', 'YER'),
    'limits' => [
        'enabled' => env('WALLET_LIMITS_ENABLED', false),
        'credit' => [
            'daily' => env('WALLET_CREDIT_DAILY_LIMIT'),
            'monthly' => env('WALLET_CREDIT_MONTHLY_LIMIT'),
        ],
        'debit' => [
            'daily' => env('WALLET_DEBIT_DAILY_LIMIT'),
            'monthly' => env('WALLET_DEBIT_MONTHLY_LIMIT'),
        ],
    ],



    'withdrawals' => [
        'minimum_amount' => (float) env('WALLET_WITHDRAWAL_MINIMUM_AMOUNT', 0),
        'methods' => [
            [
                'key' => 'bank_transfer',
                'name' => 'بنك الشرق',
                'description' => 'سحب الرصيد عبر بنك الشرق.',

                'fields' => [
                    [
                        'key' => 'account_number',
                        'label' => 'رقم الحساب',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:64'],
                    ],
                    [
                        'key' => 'contact_number',
                        'label' => 'رقم الهاتف',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:32'],
                    ],
                ],

            ],
            [
                'key' => 'cash_pickup',
                'name' => 'تحويل صرافات',
                'description' => 'سحب الرصيد عبر الصرافات.',
                'fields' => [
                    [
                        'key' => 'recipient_name',
                        'label' => 'اسم المستلم الرباعي',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:191'],
                    ],
                    [
                        'key' => 'contact_number',
                        'label' => 'رقم الهاتف',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:32'],
                    ],
                ],

            ],
        ],
    ],

    'notifications' => [
        'low_balance_threshold' => (float) env('WALLET_LOW_BALANCE_THRESHOLD', 1000),
        'low_balance_min_inactivity_days' => (int) env('WALLET_LOW_BALANCE_MIN_INACTIVITY_DAYS', 2),
        'low_balance_cooldown_days' => (int) env('WALLET_LOW_BALANCE_COOLDOWN_DAYS', 7),
        'inactive_balance_days' => (int) env('WALLET_INACTIVE_BALANCE_DAYS', 10),
        'inactive_balance_cooldown_days' => (int) env('WALLET_INACTIVE_BALANCE_COOLDOWN_DAYS', 10),
    ],

];
