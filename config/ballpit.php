<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Placeholder brand details for the storefront. Swap these out once the
    | real name and logo are ready.
    |
    */

    'brand_name' => env('BALLPIT_BRAND_NAME', 'Ball Pit Palette'),

    'tagline' => env('BALLPIT_TAGLINE', 'Custom plastic ball pits, mixed to your palette.'),

    'support_email' => env('BALLPIT_SUPPORT_EMAIL', 'hello@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Order rules
    |--------------------------------------------------------------------------
    */

    'currency' => env('BALLPIT_CURRENCY', 'usd'),

    'shipping_flat_cents' => (int) env('BALLPIT_SHIPPING_FLAT_CENTS', 2500),

    // The largest number of a single ball type a customer may order online.
    'max_quantity_per_type' => (int) env('BALLPIT_MAX_QUANTITY_PER_TYPE', 50000),

    /*
    |--------------------------------------------------------------------------
    | Checkout payment methods
    |--------------------------------------------------------------------------
    |
    | Payment method types offered on the Stripe Checkout session. Leave this
    | empty to let Stripe decide automatically based on the payment methods
    | enabled in your dashboard (recommended for most accounts).
    |
    | Note: PayPal through Stripe is only available to Stripe accounts based
    | in supported European countries. See the "Payments" section of README.md.
    |
    */

    'payment_method_types' => array_values(array_filter(
        explode(',', (string) env('BALLPIT_PAYMENT_METHOD_TYPES', ''))
    )),

];
