<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

use InvalidArgumentException;

/**
 * A whole number in French words, for the "total en lettres" an invoice must
 * carry (cahier §6.7). Follows the spelling rules that trip people up: "vingt
 * et un", "soixante et onze", "quatre-vingts" (plural only when nothing follows),
 * "cent" / "cents" (same), "mille" (invariable, never "un mille"), and
 * "million(s)" / "milliard(s)" as nouns.
 */
final class AmountInWords
{
    private const UNITS = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    /** "cent dix-huit mille francs CFA" - first letter capitalised by the caller if wanted. */
    public static function francsCfa(int $amount): string
    {
        $words = self::number($amount);

        // "un million de francs", "deux milliards de francs": the noun scale takes "de".
        $needsDe = $amount > 0 && $amount % 1_000_000 === 0;

        return $words.($needsDe ? ' de' : '').' franc'.($amount > 1 ? 's' : '').' CFA';
    }

    public static function number(int $n): string
    {
        if ($n < 0) {
            throw new InvalidArgumentException('Un montant en lettres ne peut pas être négatif.');
        }

        if ($n === 0) {
            return self::UNITS[0];
        }

        $parts = [];

        foreach ([1_000_000_000 => 'milliard', 1_000_000 => 'million'] as $scale => $noun) {
            $group = intdiv($n, $scale);
            $n %= $scale;

            if ($group > 0) {
                // A noun: "deux cents millions" keeps its s, "un million" / "deux millions" agree.
                $parts[] = self::belowThousand($group, true).' '.$noun.($group > 1 ? 's' : '');
            }
        }

        $thousands = intdiv($n, 1_000);
        $n %= 1_000;

        if ($thousands > 0) {
            // "mille" alone for 1 000, and no plural s on cent/vingt before it: "quatre-vingt mille".
            $parts[] = $thousands === 1 ? 'mille' : self::belowThousand($thousands, false).' mille';
        }

        if ($n > 0) {
            $parts[] = self::belowThousand($n, true);
        }

        return implode(' ', $parts);
    }

    /** 1-999. $closing = nothing follows this group, so "cents" / "quatre-vingts" take their s. */
    private static function belowThousand(int $n, bool $closing): string
    {
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $words = [];

        if ($hundreds > 0) {
            $words[] = $hundreds === 1
                ? 'cent'
                : self::UNITS[$hundreds].' cent'.(($rest === 0 && $closing) ? 's' : '');
        }

        if ($rest > 0) {
            $words[] = self::belowHundred($rest, $closing);
        }

        return implode(' ', $words);
    }

    /** 1-99. */
    private static function belowHundred(int $n, bool $closing): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        $tens = intdiv($n, 10);
        $unit = $n % 10;

        // 20-69.
        if ($tens <= 6) {
            return match (true) {
                $unit === 0 => self::TENS[$tens],
                $unit === 1 => self::TENS[$tens].' et un',
                default => self::TENS[$tens].'-'.self::UNITS[$unit],
            };
        }

        // 70-79: soixante + 10..19.
        if ($tens === 7) {
            return $unit === 1 ? 'soixante et onze' : 'soixante-'.self::UNITS[10 + $unit];
        }

        // 80-99: quatre-vingt(s) + 0..19.
        if ($n === 80) {
            return 'quatre-vingt'.($closing ? 's' : '');
        }

        return 'quatre-vingt-'.self::UNITS[$n - 80];
    }
}
