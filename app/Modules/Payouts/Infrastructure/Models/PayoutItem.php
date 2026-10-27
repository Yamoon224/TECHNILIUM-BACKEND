<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $payout_id
 * @property int $vendor_id
 * @property string $order_number
 * @property int $gross_amount
 * @property int $commission_amount
 * @property int $refund_deduction_amount
 */
class PayoutItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['payout_id', 'vendor_id', 'order_number', 'gross_amount', 'commission_amount', 'refund_deduction_amount'];
}
