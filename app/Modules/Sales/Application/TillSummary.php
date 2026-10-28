<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Illuminate\Support\Carbon;

/**
 * What a cashier's drawer should hold since their last closing.
 *
 * Sales still uncounted add to it; refunds of cancelled sales not yet counted take away from it —
 * including a sale cancelled today that was counted in an earlier closing, so a closing never
 * rewrites the past. A sale made and cancelled inside the same period nets to zero.
 */
final class TillSummary
{
    /**
     * @return array{sales_count: int, cancellations_count: int, cash_sales: int, cash_refunds: int, expected_cash: int, mobile_money_net: int, period_start: Carbon|null, currency: string}
     */
    public function forCashier(int $userId): array
    {
        $sales = InternalSale::query()->where('seller_id', $userId)->whereNull('closing_id')->get();
        $refunds = InternalSale::query()->where('cancelled_by', $userId)->whereNotNull('cancelled_at')->whereNull('cancel_closing_id')->get();

        $cashSales = (int) $sales->where('method', 'cash')->sum('amount');
        $cashRefunds = (int) $refunds->where('method', 'cash')->sum('amount');
        $mobileNet = (int) $sales->where('method', 'mobile_money')->sum('amount') - (int) $refunds->where('method', 'mobile_money')->sum('amount');

        $start = $sales->pluck('created_at')->merge($refunds->pluck('cancelled_at'))->filter()->min();

        return [
            'sales_count' => $sales->count(),
            'cancellations_count' => $refunds->count(),
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'expected_cash' => $cashSales - $cashRefunds,
            'mobile_money_net' => $mobileNet,
            'period_start' => $start,
            'currency' => (string) ($sales->first()?->currency ?? $refunds->first()?->currency ?? 'XOF'),
        ];
    }
}
