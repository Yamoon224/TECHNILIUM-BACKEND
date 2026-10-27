<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Actions;

use App\Modules\Customers\Application\ReviewEligibility;
use App\Modules\Customers\Domain\Exceptions\ReviewException;
use App\Modules\Customers\Domain\ReviewScreener;
use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A verified buyer reviews a product. A clean review is published at once; one
 * the screening flags (link, contact details, banned word) waits for a person.
 */
final class SubmitReviewAction
{
    public function __construct(private readonly ReviewEligibility $eligibility) {}

    public function execute(int $customerId, string $fullName, int $productId, int $rating, ?string $title, ?string $body): Review
    {
        $check = $this->eligibility->check($customerId, $productId);

        if (! $check['can_review']) {
            throw match ($check['reason']) {
                ReviewEligibility::ALREADY_REVIEWED => ReviewException::alreadyReviewed(),
                ReviewEligibility::UNAVAILABLE => ReviewException::productUnavailable(),
                default => ReviewException::notPurchased(),
            };
        }

        $needsManual = ReviewScreener::needsManualReview(
            trim(($title ?? '').' '.($body ?? '')),
            (array) config('reviews.banned_words'),
        );
        $status = $needsManual ? ReviewStatus::Pending : ReviewStatus::Published;

        try {
            return Review::query()->create([
                'product_id' => $productId,
                'customer_id' => $customerId,
                'order_number' => (string) $check['order_number'],
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'author_name' => self::publicName($fullName),
                'status' => $status,
                'published_at' => $status === ReviewStatus::Published ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two simultaneous submissions: the unique (product, customer) index lets one win.
            throw ReviewException::alreadyReviewed();
        }
    }

    /** "Awa Diallo" -> "Awa D."; a single name stays as is. Never the full name in public. */
    public static function publicName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'Client';
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1];

        return count($parts) === 1 ? $first : $first.' '.mb_strtoupper(mb_substr($last, 0, 1)).'.';
    }
}
