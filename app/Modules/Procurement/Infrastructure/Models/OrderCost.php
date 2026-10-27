<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cost of sales recognised for a delivered order.
 *
 * @property int $order_id
 * @property string $order_number
 * @property int $cost_amount
 */
class OrderCost extends Model
{
    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $fillable = ['order_id', 'order_number', 'cost_amount'];
}
