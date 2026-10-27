<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_line_id
 * @property int $quantity_accepted
 * @property int $quantity_rejected
 * @property int $landed_amount
 */
class GoodsReceiptLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'purchase_order_line_id', 'quantity_accepted', 'quantity_rejected', 'landed_amount'];
}
