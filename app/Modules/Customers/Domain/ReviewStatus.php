<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

enum ReviewStatus: string
{
    /** Held for a human decision (the automatic screening flagged it). */
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
}
