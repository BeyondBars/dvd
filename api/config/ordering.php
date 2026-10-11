<?php

return [
    // The truck's local time: opening hours and pickup times are in this zone.
    'timezone' => env('ORDERING_TIMEZONE', 'Australia/Adelaide'),

    // The customer website, for links in customer emails (e.g. password reset).
    'website_url' => env('WEBSITE_URL', 'http://localhost:3000'),

    // Pickup times are offered in steps of this many minutes.
    'slot_minutes' => 15,

    // Most lines and items one online order may have.
    'max_lines' => 30,
    'max_quantity' => 20,
];
