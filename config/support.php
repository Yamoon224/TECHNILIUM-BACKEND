<?php

return [
    // First-response target in hours, by ticket priority (cahier §6.12).
    'first_response_hours' => [
        'high' => (int) env('SUPPORT_RESPONSE_HOURS_HIGH', 2),
        'normal' => (int) env('SUPPORT_RESPONSE_HOURS_NORMAL', 8),
        'low' => (int) env('SUPPORT_RESPONSE_HOURS_LOW', 24),
    ],

    // How customers can reach the team outside the ticket system. Empty by default: these are the
    // company's to supply, and the public help page shows only what is set.
    'contact' => [
        'phone' => env('SUPPORT_CONTACT_PHONE', ''),
        'whatsapp' => env('SUPPORT_CONTACT_WHATSAPP', ''),
        'email' => env('SUPPORT_CONTACT_EMAIL', ''),
        'hours' => env('SUPPORT_CONTACT_HOURS', ''),
    ],
];
