<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application;

use App\Modules\Catalog\Application\Contracts\ProductLookupInterface;
use App\Modules\Customers\Infrastructure\Models\Review;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;

/**
 * Can this customer review this product? Yes only if a delivered order of
 * theirs contained it and they have not reviewed it yet - the check behind
 * "avis réservés aux acheteurs vérifiés" (cahier §5.9). Used both to tell the
 * storefront whether to show the form and to enforce it on submission.
 */
final class ReviewEligibility
{
    public const NOT_PURCHASED = 'not_purchased';

    public const ALREADY_REVIEWED = 'already_reviewed';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly ProductLookupInterface $products,
    ) {}

    /**
     * @return array{can_review: bool, reason: string|null, order_number: string|null}
     */
    public function check(int $customerId, int $productId): array
    {
        if (! $this->products->isPublished($productId)) {
            return ['can_review' => false, 'reason' => self::UNAVAILABLE, 'order_number' => null];
        }

        if (Review::query()->where('product_id', $productId)->where('customer_id', $customerId)->exists()) {
            return ['can_review' => false, 'reason' => self::ALREADY_REVIEWED, 'order_number' => null];
        }

        $delivered = $this->orders->deliveredOffersForCustomer($customerId);
        $productByOffer = $this->products->productIdsForOffers(array_keys($delivered));

        foreach ($productByOffer as $offerId => $deliveredProductId) {
            if ($deliveredProductId === $productId) {
                return ['can_review' => true, 'reason' => null, 'order_number' => $delivered[$offerId]];
            }
        }

        return ['can_review' => false, 'reason' => self::NOT_PURCHASED, 'order_number' => null];
    }
}
