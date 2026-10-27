<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $offer_id
 * @property int $quantity
 * @property int $cost_amount
 */
class OrderCostLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'offer_id', 'quantity', 'cost_amount'];
}
