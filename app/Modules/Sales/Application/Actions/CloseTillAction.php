<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Sales\Application\InternalSaleException;
use App\Modules\Sales\Application\TillSummary;
use App\Modules\Sales\Infrastructure\Models\CashClosing;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Illuminate\Support\Facades\DB;

/**
 * Closes the cashier's till: freezes what the system expected, records what was counted and the
 * variance, and marks every counted sale and refund so none is counted twice.
 */
final class CloseTillAction
{
    public function __construct(private readonly TillSummary $summary) {}

    public function execute(int $userId, string $userName, int $countedCash, ?string $note = null): CashClosing
    {
        return DB::transaction(function () use ($userId, $userName, $countedCash, $note) {
            $summary = $this->summary->forCashier($userId);

            if ($summary['sales_count'] === 0 && $summary['cancellations_count'] === 0) {
                throw InternalSaleException::nothingToClose();
            }

            $closing = CashClosing::query()->create([
                'user_id' => $userId,
                'user_name' => $userName,
                'currency' => $summary['currency'],
                'period_start' => $summary['period_start'],
                'closed_at' => now(),
                'sales_count' => $summary['sales_count'],
                'cancellations_count' => $summary['cancellations_count'],
                'cash_sales' => $summary['cash_sales'],
                'cash_refunds' => $summary['cash_refunds'],
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => $countedCash,
                'variance' => $countedCash - $summary['expected_cash'],
                'mobile_money_net' => $summary['mobile_money_net'],
                'note' => $note,
            ]);

            InternalSale::query()->where('seller_id', $userId)->whereNull('closing_id')->update(['closing_id' => $closing->id]);
            InternalSale::query()->where('refund_till_user_id', $userId)->whereNotNull('cancelled_at')->whereNull('cancel_closing_id')->update(['cancel_closing_id' => $closing->id]);

            return $closing;
        });
    }
}
