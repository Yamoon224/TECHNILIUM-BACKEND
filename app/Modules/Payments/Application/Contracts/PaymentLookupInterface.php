<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use App\Modules\Payments\Application\Data\CollectedPaymentData;

/**
 * Payments' public port for modules that must show or hand out the receipt of a payment
 * (Sales), without reaching into Payments' Eloquent models (cahier §9.1).
 */
interface PaymentLookupInterface
{
    /**
     * The received payment (completed, or since refunded) of each order, keyed by order number.
     * An order with none is simply absent.
     *
     * @param  list<string>  $orderNumbers
     * @return array<string, CollectedPaymentData>
     */
    public function collectedForOrders(array $orderNumbers): array;

    /**
     * The receipt PDF of an order's received payment; null when the order has none.
     *
     * @return array{filename: string, content: string}|null
     */
    public function receiptPdfForOrder(string $orderNumber, string $locale = 'fr'): ?array;
}
