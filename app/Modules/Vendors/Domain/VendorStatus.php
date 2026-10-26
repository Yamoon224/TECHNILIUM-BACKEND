<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Domain;

/**
 * Vendor onboarding workflow (cahier §4 "vendeur tiers").
 */
enum VendorStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Suspended],
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
