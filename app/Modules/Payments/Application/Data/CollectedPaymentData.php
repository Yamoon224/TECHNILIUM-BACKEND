<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

/**
 * Payments' public, read-only view of a payment that was actually received —
 * what another module (Sales) needs to point at its receipt.
 */
final readonly class CollectedPaymentData
{
    public function __construct(
        public int $paymentId,
        public string $receiptNumber,
        public string $orderNumber,
        public string $method,
        public ?string $provider,
        public int $amountAmount,
        public ?int $tenderedAmount,
        public string $currency,
        public ?string $paidAt,
    ) {}
}
