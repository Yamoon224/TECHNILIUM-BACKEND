<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Gateways;

use App\Modules\Returns\Application\Contracts\RefundLookupInterface;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;

final class EloquentRefundLookup implements RefundLookupInterface
{
    public function refundedAmount(string $orderNumber): int
    {
        return (int) ReturnRequest::query()
            ->where('order_number', $orderNumber)
            ->where('status', ReturnStatus::Refunded)
            ->sum('refunded_amount');
    }

    public function hasOpenReturn(string $orderNumber): bool
    {
        return ReturnRequest::query()
            ->where('order_number', $orderNumber)
            ->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Accepted, ReturnStatus::Received])
            ->exists();
    }
}
