<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review as the public sees it: first name and initial only, no account
 * id, no order number - just the "verified purchase" badge.
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'product_id' => $review->product_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author_name' => $review->author_name,
            'verified_purchase' => true,
            'published_at' => $review->published_at?->toIso8601String(),
        ];
    }
}
