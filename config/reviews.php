<?php

return [
    // Words that send a review to manual moderation (comma-separated). The
    // automatic screening already holds links, e-mails and phone numbers.
    'banned_words' => array_values(array_filter(array_map('trim', explode(',', (string) env('REVIEWS_BANNED_WORDS', ''))))),
];
