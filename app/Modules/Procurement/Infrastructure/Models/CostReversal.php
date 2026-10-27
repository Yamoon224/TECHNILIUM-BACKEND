<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $return_id
 * @property int $order_id
 * @property int $amount
 */
class CostReversal extends Model
{
    protected $fillable = ['return_id', 'order_id', 'amount'];
}
