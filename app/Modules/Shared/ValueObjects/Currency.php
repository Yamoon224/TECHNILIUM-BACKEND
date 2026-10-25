<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

/**
 * Supported currencies. minorUnits() gives the number of smallest units per
 * major unit (e.g. 100 for cents, 1 for XOF which has no subdivision in practice).
 */
enum Currency: string
{
    case XOF = 'XOF';
    case XAF = 'XAF';
    case EUR = 'EUR';
    case USD = 'USD';
    case GHS = 'GHS';
    case NGN = 'NGN';

    public function minorUnits(): int
    {
        return match ($this) {
            self::XOF, self::XAF => 1,
            self::EUR, self::USD, self::GHS, self::NGN => 100,
        };
    }
}
