<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Weighted-average unit cost of an own-stock offer (cahier §6.3 "valorisation au CMP").
 *
 * @property int $offer_id
 * @property int $average_cost_amount
 */
class OfferCost extends Model
{
    protected $primaryKey = 'offer_id';

    public $incrementing = false;

    protected $fillable = ['offer_id', 'average_cost_amount'];
}
