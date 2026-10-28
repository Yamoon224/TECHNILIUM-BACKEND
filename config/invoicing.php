<?php

return [
    // Standard VAT rate in percent applied to invoiced sales (order prices are
    // VAT-inclusive). Per-product/per-country rates (cahier §6.7) come later.
    'vat_percent' => (float) env('INVOICING_VAT_PERCENT', 18),

    // Issue the invoice automatically when an order is confirmed (never twice:
    // an order that already has a document is left to the accountant).
    'auto_issue' => (bool) env('INVOICING_AUTO_ISSUE', true),

    // How the customer receives their invoice by e-mail: "mail" sends the PDF through the
    // MAIL_* settings, "log" (default) sends nothing and only notes it in the log.
    'email_driver' => env('INVOICING_EMAIL_DRIVER', 'log'),

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
