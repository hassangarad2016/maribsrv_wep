<?php

return [
    'notifications' => [
        'fallback_store_name' => 'your store',
        'review' => [
            'submitted' => [
                'title' => 'Store review received',
                'body' => 'Your store ":store" has been submitted and is now under review. We will notify you once a decision is made.',
            ],
        ],
        'status' => [
            'approved' => [
                'title' => 'Store approved',
                'body' => 'Your store ":store" has been approved. You can now receive orders and sell through the store.',
            ],
            'rejected' => [
                'title' => 'Store rejected',
                'body' => 'Your store ":store" was not approved. Reason: :reason',
                'body_fallback' => 'Your store ":store" was not approved. Please update the details and resubmit.',
            ],
            'suspended' => [
                'title' => 'Store suspended',
                'body' => 'Your store ":store" has been suspended temporarily. Reason: :reason',
                'body_fallback' => 'Your store ":store" has been suspended temporarily. Please contact support for more details.',
            ],
            'pending' => [
                'title' => 'Store under review',
                'body' => 'Your store ":store" status was updated to under review. We will notify you once it is complete.',
            ],
            'draft' => [
                'title' => 'Store setup incomplete',
                'body' => 'Your store ":store" is not complete yet. Please finish the required details.',
            ],
        ],
        'activity' => [
            'order_created' => [
                'title' => 'New order in your store',
                'body' => 'A new order #:order has been placed for :amount :currency.',
            ],
        ],
    ],
];
