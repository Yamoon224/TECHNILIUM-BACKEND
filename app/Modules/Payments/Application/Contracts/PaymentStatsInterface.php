<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use DateTimeInterface;

/** Payments' public read-model for Reporting (cahier §6.14 "rapports financiers"). */
interface PaymentStatsInterface
{
    /**
     * Money collected in [from, to] (by payment date), in total and per method.
     * A payment later refunded still counts as collected; refunds are reported apart.
     *
     * @return array{collected: int, by_method: array<string, int>}
     */
    public function collectedBetween(DateTimeInterface $from, DateTimeInterface $to): array;

    /** Money given back to customers in [from, to]. */
    public function refundedBetween(DateTimeInterface $from, DateTimeInterface $to): int;
}
