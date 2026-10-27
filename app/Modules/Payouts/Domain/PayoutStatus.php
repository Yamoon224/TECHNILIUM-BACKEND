<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Domain;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function canTransitionTo(self $target): bool
    {
        return $this === self::Pending && $target === self::Paid;
    }
}
