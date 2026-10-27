<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Domain;

use App\Modules\Shared\ValueObjects\Money;
use App\Modules\Shared\ValueObjects\TauxTVA;

/**
 * Splits a VAT-inclusive total into net + VAT. The VAT is derived from the
 * total (never the other way round) so net + VAT always equals the amount
 * the customer actually paid, to the minor unit.
 */
final readonly class VatBreakdown
{
    private function __construct(
        public Money $net,
        public Money $vat,
        public Money $gross,
    ) {}

    public static function fromGross(Money $gross, TauxTVA $rate): self
    {
        $net = $gross->multiply(1 / (1 + $rate->toRatio()));

        return new self($net, $gross->subtract($net), $gross);
    }
}
