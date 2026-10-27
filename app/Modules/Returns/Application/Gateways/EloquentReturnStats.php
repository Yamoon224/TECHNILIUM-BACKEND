<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Gateways;

use App\Modules\Returns\Application\Contracts\ReturnStatsInterface;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use DateTimeInterface;

final class EloquentReturnStats implements ReturnStatsInterface
{
    public function countsByStatus(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return ReturnRequest::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total])
            ->all();
    }

    public function refundedBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) ReturnRequest::query()
            ->where('status', ReturnStatus::Refunded)
            ->whereBetween('refunded_at', [$from, $to])
            ->sum('refunded_amount');
    }
}
