<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

/** Display of an amount on a document: thousands separated by a space, F CFA suffix. */
final class Money
{
    public static function format(int $amount, string $currency = 'XOF'): string
    {
        // A non-breaking space, so "118 000 F CFA" is never split across two lines.
        $number = number_format($amount, 0, ',', "\u{00A0}");

        return in_array($currency, ['XOF', 'XAF'], true) ? "{$number}\u{00A0}F\u{00A0}CFA" : "{$number}\u{00A0}{$currency}";
    }
}
