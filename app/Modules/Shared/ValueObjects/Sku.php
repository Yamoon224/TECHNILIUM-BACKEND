<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Sku
{
    public string $value;

    private function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '' || ! preg_match('/^[A-Z0-9][A-Z0-9\-_.]{1,63}$/', $normalized)) {
            throw new InvalidArgumentException("Invalid SKU: \"{$value}\".");
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
