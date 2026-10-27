<?php

return [
    // Default marketplace commission in percent. Per-category / per-vendor /
    // volume-tier grids (cahier §6.2) will replace this single rate.
    'commission_percent' => (float) env('PAYOUTS_COMMISSION_PERCENT', 10),

    // Days after delivery before a vendor's share becomes payable (return window, cahier §3.1).
    'return_delay_days' => (int) env('PAYOUTS_RETURN_DELAY_DAYS', 7),
];
