<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property string $label
 * @property int $amount
 */
class LandedCost extends Model
{
    protected $fillable = ['purchase_order_id', 'label', 'amount'];
}
