<?php

declare(strict_types=1);

namespace App\Modules\Returns\Domain;

/**
 * RMA lifecycle (cahier §3.2 "Retour demandé / Retour reçu", §6.11):
 * request → accepted → parcel received and inspected → refunded, with a
 * refusal possible before acceptance or after inspection.
 */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Received = 'received';
    case Refunded = 'refunded';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Accepted, self::Rejected],
            self::Accepted => [self::Received],
            self::Received => [self::Refunded, self::Rejected],
            self::Refunded, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** A return that still holds the order's money in suspense (not yet decided). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Accepted, self::Received], true);
    }
}
