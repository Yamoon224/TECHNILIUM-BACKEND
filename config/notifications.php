<?php

return [
    // Channels used for customer messages. SMS and WhatsApp reach the checkout
    // phone; there is no customer e-mail at checkout yet, so none here.
    'channels' => array_filter(array_map('trim', explode(',', (string) env('NOTIFICATIONS_CHANNELS', 'sms')))),

    // How each channel actually delivers. "log" records the message in the
    // application log and the notifications table without calling anyone —
    // swap in a provider driver here once one is chosen (see ChannelResolver).
    'drivers' => [
        'sms' => env('NOTIFICATIONS_SMS_DRIVER', 'log'),
        'whatsapp' => env('NOTIFICATIONS_WHATSAPP_DRIVER', 'log'),
    ],
];
