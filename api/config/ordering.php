<?php

return [
    // The truck's local time: opening hours and pickup times are in this zone.
    'timezone' => env('ORDERING_TIMEZONE', 'Australia/Adelaide'),

    // Pickup times are offered in steps of this many minutes.
    'slot_minutes' => 15,

    // Most lines and items one online order may have.
    'max_lines' => 30,
    'max_quantity' => 20,
];
