<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $coupon_id
 * @property string $order_number
 * @property string $customer_key
 * @property int $discount_amount
 */
class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'order_number', 'customer_key', 'discount_amount'];
}
