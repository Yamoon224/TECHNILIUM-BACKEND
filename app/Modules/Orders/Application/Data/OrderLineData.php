<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Data;

/**
 * Orders' public read-only view of one order line — lets Invoicing snapshot
 * a sale without touching Orders' Eloquent models (cahier §9.1).
 */
final readonly class OrderLineData
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceAmount,
        public int $lineTotalAmount,
        /** Null for Technilium's own stock (1P). */
        public ?int $vendorId = null,
    ) {}
}
