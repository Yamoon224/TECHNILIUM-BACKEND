<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use App\Modules\Shared\Exceptions\CurrencyMismatchException;
use InvalidArgumentException;

/**
 * Immutable monetary amount stored as an integer in the currency's smallest
 * unit (cahier des charges §7 "Intégrité financière" — never floats).
 *
 * Every rounding decision in the codebase must go through this class so a
 * calculation rule exists in exactly one place (§10.2).
 */
final readonly class Money
{
    private function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    public static function ofMinorUnits(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function ofMajorUnits(float $amount, Currency $currency): self
    {
        return new self(
            (int) round($amount * $currency->minorUnits(), mode: PHP_ROUND_HALF_UP),
            $currency,
        );
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    /** Multiplies by an arbitrary factor (e.g. a VAT rate or a discount ratio), rounding half up. */
    public function multiply(float $factor): self
    {
        return new self(
            (int) round($this->amount * $factor, mode: PHP_ROUND_HALF_UP),
            $this->currency,
        );
    }

    /**
     * Splits the amount into N parts whose sum exactly equals the original
     * amount, distributing the rounding remainder across the first parts
     * (largest-remainder method) — used to split an order total across
     * sub-orders/vendors without losing or inventing a single minor unit.
     *
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot allocate money into fewer than 1 part.');
        }

        $base = intdiv($this->amount, $parts);
        $remainder = $this->amount - ($base * $parts);

        $shares = [];
        for ($i = 0; $i < $parts; $i++) {
            $share = $base + ($i < abs($remainder) ? ($remainder <=> 0) : 0);
            $shares[] = new self($share, $this->currency);
        }

        return $shares;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function toMajorUnits(): float
    {
        return $this->amount / $this->currency->minorUnits();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }
}
