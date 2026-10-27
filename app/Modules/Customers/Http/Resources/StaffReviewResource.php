<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Http\Request;

class StaffReviewResource extends MyReviewResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return parent::toArray($request) + [
            'customer_id' => $review->customer_id,
            'order_number' => $review->order_number,
            'moderated_by' => $review->moderated_by,
        ];
    }
}
