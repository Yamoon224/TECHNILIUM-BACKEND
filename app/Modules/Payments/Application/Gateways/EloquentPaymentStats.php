<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Gateways;

use App\Modules\Payments\Application\Contracts\PaymentStatsInterface;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentRefund;
use DateTimeInterface;

final class EloquentPaymentStats implements PaymentStatsInterface
{
    public function collectedBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $rows = Payment::query()
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, SUM(amount_amount) as total')
            ->groupBy('method')
            ->get();

        $byMethod = [];
        foreach ($rows as $row) {
            $byMethod[$row->method->value] = (int) $row->total;
        }

        return ['collected' => array_sum($byMethod), 'by_method' => $byMethod];
    }

    public function refundedBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) PaymentRefund::query()->whereBetween('created_at', [$from, $to])->sum('amount');
    }
}
