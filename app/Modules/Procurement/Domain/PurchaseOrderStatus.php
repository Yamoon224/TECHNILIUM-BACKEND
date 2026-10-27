<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain;

/**
 * Purchase order lifecycle (cahier §6.4): drafted, sent to the supplier,
 * then received in one or several parts. Cancelling is possible only
 * before anything has been received.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Ordered, self::Cancelled],
            self::Ordered => [self::PartiallyReceived, self::Received, self::Cancelled],
            self::PartiallyReceived => [self::PartiallyReceived, self::Received],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Goods can be received only on an order that was sent and is not yet complete. */
    public function acceptsReceipts(): bool
    {
        return $this === self::Ordered || $this === self::PartiallyReceived;
    }

    /**
     * Status implied by how much of the order is still outstanding.
     *
     * @param  int  $outstanding  units ordered but neither accepted nor rejected yet
     * @param  int  $handled  units accepted or rejected so far
     */
    public static function afterReceipt(int $outstanding, int $handled): self
    {
        return match (true) {
            $outstanding <= 0 => self::Received,
            $handled > 0 => self::PartiallyReceived,
            default => self::Ordered,
        };
    }
}
