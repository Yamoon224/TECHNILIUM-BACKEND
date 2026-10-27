<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    /** Document-number prefix; numbering is sequential per type and fiscal year (cahier §6.7). */
    public function prefix(): string
    {
        return match ($this) {
            self::Invoice => 'FAC',
            self::CreditNote => 'AV',
        };
    }
}
