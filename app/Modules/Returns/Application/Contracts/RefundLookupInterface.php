<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Contracts;

/**
 * Returns' public port for other modules (Payouts) that must know how much
 * of an order was refunded, or whether a return is still undecided, without
 * reaching into Returns' models (cahier §9.1).
 */
interface RefundLookupInterface
{
    /** Total refunded on the order across all its returns. */
    public function refundedAmount(string $orderNumber): int;

    /** True while a return on the order is requested, accepted or received. */
    public function hasOpenReturn(string $orderNumber): bool;
}
