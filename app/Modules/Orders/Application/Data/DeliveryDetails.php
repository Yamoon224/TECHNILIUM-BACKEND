<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Data;

/**
 * Deliberately phone + free-text landmarks rather than a strict postal
 * address — matches the cahier's note on African addressing (§5.6):
 * "repères, quartiers... plutôt qu'une adresse postale stricte".
 */
final readonly class DeliveryDetails
{
    public function __construct(
        public string $customerName,
        public string $customerPhone,
        public string $address,
        public ?string $city,
        public ?string $notes,
        public ?string $customerEmail = null,
    ) {}
}
