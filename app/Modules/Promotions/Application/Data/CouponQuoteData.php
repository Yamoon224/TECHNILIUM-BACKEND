<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Data;

/**
 * Promotions' public answer about a coupon — the only shape other modules
 * (Orders) may depend on (cahier §9.1). `reason` explains a refusal and is
 * safe to show the customer.
 */
final readonly class CouponQuoteData
{
    private function __construct(
        public bool $valid,
        public string $code,
        public int $discountAmount,
        public ?string $reason,
    ) {}

    public static function accepted(string $code, int $discountAmount): self
    {
        return new self(true, $code, $discountAmount, null);
    }

    public static function refused(string $code, string $reason): self
    {
        return new self(false, $code, 0, $reason);
    }
}
