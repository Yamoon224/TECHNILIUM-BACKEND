<?php

declare(strict_types=1);

namespace App\Modules\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * A device serial number or IMEI, used across Inventory/Orders/Returns for
 * per-unit traceability (cahier des charges §6.3, §6.11).
 */
final readonly class NumeroSerie
{
    public string $value;

    private function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '' || mb_strlen($normalized) > 64) {
            throw new InvalidArgumentException("Invalid serial number: \"{$value}\".");
        }

        if (ctype_digit($normalized) && mb_strlen($normalized) === 15 && ! self::passesLuhnCheck($normalized)) {
            throw new InvalidArgumentException("Invalid IMEI checksum: \"{$value}\".");
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isImei(): bool
    {
        return ctype_digit($this->value) && mb_strlen($this->value) === 15;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function passesLuhnCheck(string $digits): bool
    {
        $sum = 0;
        $alternate = false;

        for ($i = mb_strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
