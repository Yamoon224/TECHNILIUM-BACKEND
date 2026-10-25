<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/**
 * Offer moderation workflow (cahier §6.1 "file de validation").
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Archived],
            self::PendingReview => [self::Published, self::Rejected],
            self::Published => [self::Archived],
            self::Rejected => [self::Draft, self::PendingReview],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
