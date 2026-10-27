<?php

return [
    // Channels used for customer messages. SMS and WhatsApp reach the checkout
    // phone; "email" reaches the optional address given at checkout (skipped
    // when there is none).
    'channels' => array_filter(array_map('trim', explode(',', (string) env('NOTIFICATIONS_CHANNELS', 'sms,email')))),

    // How each channel actually delivers. "log" records the message in the
    // application log and the notifications table without calling anyone —
    // swap in a provider driver here once one is chosen (see ChannelResolver).
    'drivers' => [
        'sms' => env('NOTIFICATIONS_SMS_DRIVER', 'log'),
        // "mail" sends through the application mailer (MAIL_* settings).
        'email' => env('NOTIFICATIONS_EMAIL_DRIVER', 'log'),
        'whatsapp' => env('NOTIFICATIONS_WHATSAPP_DRIVER', 'log'),
    ],
];
