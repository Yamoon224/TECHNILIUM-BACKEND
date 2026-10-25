<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Quantity
{
    private function __construct(public int $value) {}

    public static function of(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(self $other): self
    {
        if ($other->value > $this->value) {
            throw new InvalidArgumentException('Resulting quantity cannot be negative.');
        }

        return new self($this->value - $other->value);
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->value >= $other->value;
    }
}
