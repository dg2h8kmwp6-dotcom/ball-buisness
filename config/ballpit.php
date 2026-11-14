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
    | Ball photo storage
    |--------------------------------------------------------------------------
    |
    | Disk that uploaded ball photos are written to. Locally this is the
    | "public" disk (run `php artisan storage:link` once). On Laravel Cloud the
    | filesystem is ephemeral, so point this at a public Object Storage bucket
    | attached to the environment with the disk name "public".
    |
    */

    'image_disk' => env('BALLPIT_IMAGE_DISK', 'public'),

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
    | Quick-add amounts
    |--------------------------------------------------------------------------
    |
    | The one-tap "+N" buttons on each ball type in the order builder. The
    | +/- stepper uses each ball type's own quantity_step instead, which the
    | admin area can set per colour.
    |
    */

    'quick_add_quantities' => [5, 10, 20, 50],

    /*
    |--------------------------------------------------------------------------
    | Shipping destinations
    |--------------------------------------------------------------------------
    |
    | Countries we will ship to, as ISO 3166-1 alpha-2 codes. Add to this list
    | to open up new destinations.
    |
    | Note that `shipping_flat_cents` above is a single flat rate for every
    | destination on this list, so review it before adding countries that cost
    | materially more to reach.
    |
    */

    'shipping_countries' => [
        'US' => 'United States',
        'CA' => 'Canada',
    ],

    // Label used for the region/state field, per country.
    'region_labels' => [
        'US' => 'State',
        'CA' => 'Province',
    ],

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
