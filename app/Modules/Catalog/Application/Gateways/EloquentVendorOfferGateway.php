<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Gateways;

use App\Modules\Catalog\Application\Actions\TransitionOfferStatusAction;
use App\Modules\Catalog\Application\Contracts\VendorOfferGatewayInterface;
use App\Modules\Catalog\Application\Data\VendorOfferData;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Shared\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Builder;

final class EloquentVendorOfferGateway implements VendorOfferGatewayInterface
{
    public function variantExists(int $variantId): bool
    {
        return ProductVariant::query()->whereKey($variantId)->exists();
    }

    public function createOffer(int $variantId, int $vendorId, array $data): VendorOfferData
    {
        $offer = Offer::query()->create([
            'product_variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'price_amount' => $data['price_amount'],
            'currency' => $data['currency'] ?? Currency::XOF->value,
            'condition_grade' => $data['condition_grade'] ?? null,
            'warranty_months' => $data['warranty_months'] ?? 0,
            'stock_quantity' => $data['stock_quantity'],
            // Vendor-submitted listings go through moderation before going
            // live (cahier §6.1 "file de validation") - unlike staff-entered
            // catalog data, which publishes immediately.
            'status' => OfferStatus::PendingReview->value,
        ]);

        return $this->toData($offer->loadMissing('variant.product'));
    }

    public function findOwnedByVendor(int $offerId, int $vendorId): ?VendorOfferData
    {
        $offer = $this->ownedOfferQuery($offerId, $vendorId)->first();

        return $offer ? $this->toData($offer) : null;
    }

    public function updateOffer(int $offerId, int $vendorId, array $data): ?VendorOfferData
    {
        $offer = $this->ownedOfferQuery($offerId, $vendorId)->first();

        if (! $offer || $offer->status === OfferStatus::Archived) {
            return null;
        }

        $pricingChanged = $this->pricingFieldsChanged($offer, $data);

        $offer->fill(array_intersect_key($data, array_flip(['price_amount', 'stock_quantity', 'warranty_months'])));
        $offer->save();

        // A live listing whose price or warranty changes goes back through
        // moderation rather than updating silently - stock-only edits don't
        // (cahier §6.1 "file de validation").
        if ($pricingChanged && $offer->status === OfferStatus::Published) {
            (new TransitionOfferStatusAction)->execute($offer, OfferStatus::PendingReview);
        }

        return $this->toData($offer->refresh());
    }

    public function archiveOffer(int $offerId, int $vendorId): bool
    {
        $offer = Offer::query()->where('vendor_id', $vendorId)->find($offerId);

        if (! $offer) {
            return false;
        }

        if ($offer->status !== OfferStatus::Archived) {
            (new TransitionOfferStatusAction)->execute($offer, OfferStatus::Archived);
        }

        return true;
    }

    public function archiveAllOffersForVendor(int $vendorId): void
    {
        Offer::query()
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', OfferStatus::Archived->value)
            ->get()
            ->each(fn (Offer $offer) => (new TransitionOfferStatusAction)->execute($offer, OfferStatus::Archived));
    }

    /**
     * @param  array{price_amount?: int, stock_quantity?: int, warranty_months?: int}  $data
     */
    private function pricingFieldsChanged(Offer $offer, array $data): bool
    {
        foreach (['price_amount', 'warranty_months'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $offer->{$field}) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<VendorOfferData>
     */
    public function listOffersForVendor(int $vendorId): array
    {
        return Offer::query()
            ->where('vendor_id', $vendorId)
            ->with('variant.product')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Offer $offer) => $this->toData($offer))
            ->all();
    }

    /**
     * @return Builder<Offer>
     */
    private function ownedOfferQuery(int $offerId, int $vendorId)
    {
        return Offer::query()->where('vendor_id', $vendorId)->where('id', $offerId)->with('variant.product');
    }

    private function toData(Offer $offer): VendorOfferData
    {
        return new VendorOfferData(
            offerId: $offer->id,
            variantId: $offer->product_variant_id,
            vendorId: (int) $offer->vendor_id,
            productName: $offer->variant->product->name,
            brand: $offer->variant->product->brand,
            sku: $offer->variant->sku,
            priceAmount: $offer->price_amount,
            currency: $offer->currency,
            stockQuantity: $offer->stock_quantity,
            warrantyMonths: $offer->warranty_months,
            status: $offer->status->value,
        );
    }
}
