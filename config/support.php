<?php

return [
    // First-response target in hours, by ticket priority (cahier §6.12).
    'first_response_hours' => [
        'high' => (int) env('SUPPORT_RESPONSE_HOURS_HIGH', 2),
        'normal' => (int) env('SUPPORT_RESPONSE_HOURS_NORMAL', 8),
        'low' => (int) env('SUPPORT_RESPONSE_HOURS_LOW', 24),
    ],
];
