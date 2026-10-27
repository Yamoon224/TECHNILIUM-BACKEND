<?php

return [
    // Standard VAT rate in percent applied to invoiced sales (order prices are
    // VAT-inclusive). Per-product/per-country rates (cahier §6.7) come later.
    'vat_percent' => (float) env('INVOICING_VAT_PERCENT', 18),

    // The issuer printed on every document (cahier §6.7 "mentions légales").
    // Deliberately empty by default: legal identifiers (RCCM, numéro de compte
    // contribuable, capital...) are the company's to supply, never invented here.
    'company' => [
        'name' => env('INVOICING_COMPANY_NAME', 'Technilium'),
        'address' => env('INVOICING_COMPANY_ADDRESS', ''),
        'phone' => env('INVOICING_COMPANY_PHONE', ''),
        'email' => env('INVOICING_COMPANY_EMAIL', ''),
        'legal' => env('INVOICING_COMPANY_LEGAL', ''),
    ],
];
