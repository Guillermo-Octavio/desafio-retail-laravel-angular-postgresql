<?php

return [

    /*
    | Maximum amount per currency, in minor units (SUP-04).
    */
    'amount_limits' => [
        'CLP' => (int) env('PAYMENTS_AMOUNT_LIMIT_CLP', 5000000),
        'USD' => (int) env('PAYMENTS_AMOUNT_LIMIT_USD', 500000),
    ],

    /*
    | Retries of temporary acquirer errors (SUP-10, architecture section 5.3).
    */
    'acquirer' => [
        'max_attempts' => (int) env('ACQUIRER_MAX_ATTEMPTS', 3),
        'initial_backoff_ms' => (int) env('ACQUIRER_INITIAL_BACKOFF_MS', 200),
        'max_jitter_ms' => (int) env('ACQUIRER_MAX_JITTER_MS', 100),
    ],

];
