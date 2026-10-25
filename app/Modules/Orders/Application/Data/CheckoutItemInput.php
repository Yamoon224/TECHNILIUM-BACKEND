<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Data;

final readonly class CheckoutItemInput
{
    public function __construct(
        public int $offerId,
        public int $quantity,
    ) {}
}
