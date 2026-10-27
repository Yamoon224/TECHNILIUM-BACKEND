<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Actions;

use App\Modules\Customers\Domain\Exceptions\ReviewException;
use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Support\Facades\DB;

/** A person decides on a review: publish it, or reject it (with the reason shown to its author). */
final class ModerateReviewAction
{
    public function publish(Review $review, int $moderatorId): Review
    {
        return $this->move($review, ReviewStatus::Published, $moderatorId, null);
    }

    public function reject(Review $review, int $moderatorId, ?string $reason): Review
    {
        return $this->move($review, ReviewStatus::Rejected, $moderatorId, $reason);
    }

    private function move(Review $review, ReviewStatus $target, int $moderatorId, ?string $reason): Review
    {
        return DB::transaction(function () use ($review, $target, $moderatorId, $reason) {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->status === $target) {
                throw ReviewException::alreadyInState($target->value);
            }

            $review->status = $target;
            $review->moderated_by = $moderatorId;
            $review->rejection_reason = $target === ReviewStatus::Rejected ? $reason : null;
            $review->published_at = $target === ReviewStatus::Published ? now() : null;
            $review->save();

            return $review;
        });
    }
}
