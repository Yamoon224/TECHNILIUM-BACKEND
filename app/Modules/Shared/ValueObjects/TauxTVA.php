<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * VAT rate expressed in basis points (1/100th of a percent) to avoid float
 * drift — e.g. 1800 = 18.00%.
 */
final readonly class TauxTVA
{
    private function __construct(public int $basisPoints) {}

    public static function ofPercent(float $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('VAT rate cannot be negative.');
        }

        return new self((int) round($percent * 100, mode: PHP_ROUND_HALF_UP));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toRatio(): float
    {
        return $this->basisPoints / 10_000;
    }

    public function applyTo(Money $amount): Money
    {
        return $amount->multiply($this->toRatio());
    }

    public function toPercent(): float
    {
        return $this->basisPoints / 100;
    }
}
