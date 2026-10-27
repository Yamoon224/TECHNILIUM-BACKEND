<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One refund of (part of) a payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $amount
 * @property string|null $reason
 * @property int|null $created_by
 */
class PaymentRefund extends Model
{
    protected $fillable = ['payment_id', 'amount', 'reason', 'created_by'];
}
