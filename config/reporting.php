<?php

return [
    // A confirmed/pending order older than this many days is flagged as stalled (cahier §6.5 "alertes").
    'stalled_after_days' => (int) env('REPORTING_STALLED_AFTER_DAYS', 3),

    // Offers at or below this stock level raise a stock alert (cahier §6.3 "seuils").
    'low_stock_threshold' => (int) env('REPORTING_LOW_STOCK_THRESHOLD', 5),

    // Longest period a report may span, to keep queries bounded.
    'max_period_days' => 366,
];
