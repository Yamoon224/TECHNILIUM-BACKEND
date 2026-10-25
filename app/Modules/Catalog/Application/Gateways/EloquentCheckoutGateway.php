<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Gateways;

use App\Modules\Catalog\Application\Contracts\CheckoutGatewayInterface;
use App\Modules\Catalog\Application\Data\CheckoutOfferData;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Shared\ValueObjects\Currency;

final class EloquentCheckoutGateway implements CheckoutGatewayInterface
{
    public function find(int $offerId): ?CheckoutOfferData
    {
        $offer = Offer::query()
            ->with('variant.product')
            ->where('status', OfferStatus::Published)
            ->find($offerId);

        if (! $offer) {
            return null;
        }

        return new CheckoutOfferData(
            offerId: $offer->id,
            productName: $offer->variant->product->name,
            brand: $offer->variant->product->brand,
            priceAmount: $offer->price_amount,
            currency: Currency::from($offer->currency),
            stockQuantity: $offer->stock_quantity,
        );
    }

    public function decrementStock(int $offerId, int $quantity): void
    {
        /** @var Offer|null $offer */
        $offer = Offer::query()->lockForUpdate()->find($offerId);

        if (! $offer || $offer->stock_quantity < $quantity) {
            throw InsufficientStockException::forOffer($offerId, $quantity, $offer->stock_quantity ?? 0);
        }

        $offer->decrement('stock_quantity', $quantity);
    }
}
