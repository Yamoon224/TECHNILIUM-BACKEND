<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Gateways;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Catalog\Application\Data\OfferStockData;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use Illuminate\Support\Facades\DB;

final class EloquentInventoryGateway implements InventoryGatewayInterface
{
    public function find(int $offerId): ?OfferStockData
    {
        $offer = Offer::query()->with('variant.product')->find($offerId);

        return $offer ? $this->toData($offer) : null;
    }

    public function adjustStock(int $offerId, int $delta): int
    {
        return DB::transaction(function () use ($offerId, $delta): int {
            /** @var Offer $offer */
            $offer = Offer::query()->lockForUpdate()->findOrFail($offerId);

            $newQuantity = $offer->stock_quantity + $delta;

            if ($newQuantity < 0) {
                throw InsufficientStockException::forOffer($offerId, abs($delta), $offer->stock_quantity);
            }

            $offer->stock_quantity = $newQuantity;
            $offer->save();

            return $newQuantity;
        });
    }

    /**
     * @return list<OfferStockData>
     */
    public function listBelowThreshold(int $threshold): array
    {
        return Offer::query()
            ->where('stock_quantity', '<=', $threshold)
            ->whereIn('status', [OfferStatus::Published->value, OfferStatus::PendingReview->value])
            ->with('variant.product')
            ->orderBy('stock_quantity')
            ->get()
            ->map(fn (Offer $offer) => $this->toData($offer))
            ->all();
    }

    private function toData(Offer $offer): OfferStockData
    {
        return new OfferStockData(
            offerId: $offer->id,
            productName: $offer->variant->product->name,
            brand: $offer->variant->product->brand,
            sku: $offer->variant->sku,
            stockQuantity: $offer->stock_quantity,
            vendorId: $offer->vendor_id,
        );
    }
}
