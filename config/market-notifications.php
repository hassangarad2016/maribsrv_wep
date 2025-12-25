<?php

return [
    'currency' => [
        'spike_enabled' => (bool) env('CURRENCY_SPIKE_ENABLED', true),
        'spike_percent' => (float) env('CURRENCY_SPIKE_PERCENT', 3),
        'spike_window_minutes' => (int) env('CURRENCY_SPIKE_WINDOW_MINUTES', 120),
    ],
    'metal' => [
        'spike_enabled' => (bool) env('METAL_SPIKE_ENABLED', true),
        'spike_percent' => (float) env('METAL_SPIKE_PERCENT', 2),
        'spike_window_minutes' => (int) env('METAL_SPIKE_WINDOW_MINUTES', 120),
    ],
];
