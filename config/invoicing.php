<?php

return [
    // Standard VAT rate in percent applied to invoiced sales (order prices are
    // VAT-inclusive). Per-product/per-country rates (cahier §6.7) come later.
    'vat_percent' => (float) env('INVOICING_VAT_PERCENT', 18),
];
