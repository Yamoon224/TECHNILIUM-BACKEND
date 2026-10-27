<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain;

enum CouponType: string
{
    /** `value` is a whole percentage, 1-100. */
    case Percent = 'percent';

    /** `value` is an amount in minor units. */
    case Fixed = 'fixed';
}
