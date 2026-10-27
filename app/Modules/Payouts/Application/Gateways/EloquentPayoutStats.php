<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Gateways;

use App\Modules\Payouts\Application\Contracts\PayoutStatsInterface;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use DateTimeInterface;

final class EloquentPayoutStats implements PayoutStatsInterface
{
    public function owedToVendors(): int
    {
        return (int) Payout::query()->where('status', PayoutStatus::Pending)->sum('net_amount');
    }

    public function commissionsBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Payout::query()->whereBetween('created_at', [$from, $to])->sum('commission_amount');
    }

    public function paidBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Payout::query()
            ->where('status', PayoutStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('net_amount');
    }
}
