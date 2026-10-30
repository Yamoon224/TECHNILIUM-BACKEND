<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Data;

/**
 * Payouts' public, read-only view of a payout for the vendor it belongs to -
 * the only shape other modules (Vendors) may depend on (cahier §9.1).
 */
final readonly class VendorPayoutData
{
    public function __construct(
        public int $id,
        public string $status,
        public string $currency,
        public float $commissionRatePercent,
        public int $grossAmount,
        public int $commissionAmount,
        public int $netAmount,
        public ?string $paymentReference,
        public ?string $paidAt,
        public ?string $createdAt,
        /** @var list<array{order_number: string, gross_amount: int, commission_amount: int, refund_deduction_amount: int}>|null */
        public ?array $items = null,
    ) {}
}
