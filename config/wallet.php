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
        'minimum_amount' => (float) env('WALLET_WITHDRAWAL_MINIMUM_AMOUNT', 1),
        'methods' => [
            [
                'key' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Receive funds via bank transfer.',

                'fields' => [
                    [
                        'key' => 'account_name',
                        'label' => 'Account name',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:191'],
                    ],
                    [
                        'key' => 'account_number',
                        'label' => 'Account number',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:64'],
                    ],
                    [
                        'key' => 'bank_name',
                        'label' => 'Bank name',
                        'required' => false,
                        'rules' => ['nullable', 'string', 'max:191'],
                    ],
                    [
                        'key' => 'iban',
                        'label' => 'IBAN',
                        'required' => false,
                        'rules' => ['nullable', 'string', 'max:34'],
                    ],
                ],

            ],
            [
                'key' => 'cash_pickup',
                'name' => 'Cash Pickup',
                'description' => 'Collect cash from the service desk.',
                'fields' => [
                    [
                        'key' => 'recipient_name',
                        'label' => 'Recipient name',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:191'],
                    ],
                    [
                        'key' => 'contact_number',
                        'label' => 'Contact number',
                        'required' => true,
                        'rules' => ['required', 'string', 'max:32'],
                    ],
                    [
                        'key' => 'national_id',
                        'label' => 'National ID',
                        'required' => false,
                        'rules' => ['nullable', 'string', 'max:64'],
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
