<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Domain;

/**
 * Commission rule, isolated so a different strategy (per category, per
 * vendor, volume tier) can replace it without touching the payout action
 * (cahier §10.1, O). Rate in basis points; rounding half up, in one place.
 */
final readonly class CommissionCalculator
{
    public function __construct(public int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new \InvalidArgumentException('Commission must be between 0 and 100 %.');
        }
    }

    public static function fromPercent(float $percent): self
    {
        return new self((int) round($percent * 100));
    }

    public function commissionOn(int $grossAmount): int
    {
        return intdiv($grossAmount * $this->basisPoints + 5_000, 10_000);
    }
}
