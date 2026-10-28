<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An end-of-day till closing. Written once, never edited: it is the cashier's signed count.
 *
 * @property int $id
 * @property int $user_id
 * @property string $user_name
 * @property string $currency
 * @property Carbon|null $period_start
 * @property Carbon $closed_at
 * @property int $sales_count
 * @property int $cancellations_count
 * @property int $cash_sales
 * @property int $cash_refunds
 * @property int $expected_cash
 * @property int $counted_cash
 * @property int $variance
 * @property int $mobile_money_net
 * @property string|null $note
 */
class CashClosing extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'currency', 'period_start', 'closed_at', 'sales_count', 'cancellations_count',
        'cash_sales', 'cash_refunds', 'expected_cash', 'counted_cash', 'variance', 'mobile_money_net', 'note',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'closed_at' => 'datetime'];
    }
}
