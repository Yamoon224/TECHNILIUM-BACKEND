<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

use InvalidArgumentException;

/**
 * A whole number in English words, for the "amount in words" line of an
 * English invoice: "one hundred eighteen thousand CFA francs". Hyphenated
 * tens ("twenty-one"), no "and", scales by thousand / million / billion.
 */
final class AmountInWordsEn
{
    private const UNITS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

    public static function francsCfa(int $amount): string
    {
        return self::number($amount).' CFA franc'.($amount === 1 ? '' : 's');
    }

    public static function number(int $n): string
    {
        if ($n < 0) {
            throw new InvalidArgumentException('An amount in words cannot be negative.');
        }

        if ($n === 0) {
            return self::UNITS[0];
        }

        $parts = [];

        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand'] as $scale => $noun) {
            $group = intdiv($n, $scale);
            $n %= $scale;

            if ($group > 0) {
                $parts[] = self::belowThousand($group).' '.$noun;
            }
        }

        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string
    {
        $words = [];

        if ($n >= 100) {
            $words[] = self::UNITS[intdiv($n, 100)].' hundred';
            $n %= 100;
        }

        if ($n >= 20) {
            $tens = self::TENS[intdiv($n, 10)];
            $words[] = $n % 10 === 0 ? $tens : $tens.'-'.self::UNITS[$n % 10];
        } elseif ($n > 0) {
            $words[] = self::UNITS[$n];
        }

        return implode(' ', $words);
    }
}
