<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session pricing
    |--------------------------------------------------------------------------
    |
    | One-off price to run a bidding session (Part 3.7). Stored in minor units
    | (sen). The provider is deferred (Q2); until one is wired the gate is a
    | sandbox that marks the session paid without charging.
    |
    */

    'session_price_cents' => (int) env('SESSION_PRICE_CENTS', 1000), // RM 10.00

    'currency' => env('BILLING_CURRENCY', 'MYR'),

    // 'stub' for the sandbox gate; swap for 'toyyibpay' | 'billplz' | 'stripe'.
    'provider' => env('PAYMENT_PROVIDER', 'stub'),

];
