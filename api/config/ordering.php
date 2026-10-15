<?php

return [
    // The truck's local time: opening hours and pickup times are in this zone.
    'timezone' => env('ORDERING_TIMEZONE', 'Australia/Adelaide'),

    // The customer website, for links in customer emails (e.g. password reset).
    'website_url' => env('WEBSITE_URL', 'http://localhost:3000'),

    // Pickup times are offered in steps of this many minutes.
    'slot_minutes' => 15,

    // The wait time shown on the website and used for the earliest pickup time.
    'wait' => [
        // Added for each order still to be cooked ahead of a new one.
        'minutes_per_order' => 3,
        // Prep time is learned from the last orders (accepted to ready) once there are this many...
        'learn_min_orders' => 5,
        'learn_orders' => 30,
        'learn_days' => 14,
        // ...and kept within these bounds, so one forgotten "Ready" tap can't skew it.
        'min_prep_minutes' => 5,
        'max_prep_minutes' => 60,
        // Orders in the queue: up to this many is "quiet", up to this many "steady", more is "slammed".
        'busy_levels' => ['quiet' => 2, 'steady' => 5],
        // How long the website's live numbers are cached.
        'cache_seconds' => 30,
    ],

    // Most lines and items one online order may have.
    'max_lines' => 30,
    'max_quantity' => 20,
];
