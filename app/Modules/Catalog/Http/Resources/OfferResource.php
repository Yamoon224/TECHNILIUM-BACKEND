<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Shared\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'seller' => $offer->isSoldByTechnilium() ? 'technilium' : 'vendor',
            'vendor_id' => $offer->vendor_id,
            'price' => $this->money($offer->price()),
            'compare_at_price' => ($compareAt = $offer->compareAtPrice()) ? $this->money($compareAt) : null,
            'condition_grade' => $offer->condition_grade,
            'warranty_months' => $offer->warranty_months,
            'status' => $offer->status->value,
        ];
    }

    /**
     * @return array{amount: int, currency: string}
     */
    private function money(Money $money): array
    {
        return [
            'amount' => $money->amount,
            'currency' => $money->currency->value,
        ];
    }
}
