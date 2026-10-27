<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Contracts;

use App\Modules\Promotions\Application\Data\CouponQuoteData;

/**
 * Promotions' public port for Orders (and the storefront's cart preview).
 * Amounts are VAT-inclusive minor units of the cart subtotal.
 */
interface CouponGatewayInterface
{
    /** What the coupon would take off — no side effect. */
    public function quote(string $code, int $subtotal, string $customerKey): CouponQuoteData;

    /**
     * Same checks, then records the use atomically (usage counters are locked,
     * so the last use of a capped coupon cannot be taken twice). Must run in
     * the transaction that creates the order: if the order fails, the use rolls back.
     */
    public function redeem(string $code, int $subtotal, string $customerKey, string $orderNumber): CouponQuoteData;
}
