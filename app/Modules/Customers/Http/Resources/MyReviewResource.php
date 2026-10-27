<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Http\Request;

/** The author's own view: adds the moderation state and, if rejected, why. */
class MyReviewResource extends ReviewResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return parent::toArray($request) + [
            'status' => $review->status->value,
            'rejection_reason' => $review->rejection_reason,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
