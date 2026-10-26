<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

/**
 * Payment lifecycle (cahier §5.2 "paiement à la livraison / mobile money").
 * No live provider integration exists yet — a mobile money payment is
 * confirmed manually by Comptable once the operator's SMS/USSD
 * confirmation is checked, the common pattern before a webhook
 * integration is trusted or available.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Completed, self::Failed],
            // A failed attempt is confirmed directly once the actual
            // transaction reference is found (e.g. a delayed mobile money
            // confirmation) — there's no separate "retry" step/endpoint.
            self::Failed => [self::Completed],
            self::Completed => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
