<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Contracts;

use DateTimeInterface;

/** Returns' public read-model for Reporting (cahier §6.11 "statistiques de qualité"). */
interface ReturnStatsInterface
{
    /**
     * Returns opened in [from, to] per status.
     *
     * @return array<string, int>
     */
    public function countsByStatus(DateTimeInterface $from, DateTimeInterface $to): array;

    /** Value refunded through returns in [from, to]. */
    public function refundedBetween(DateTimeInterface $from, DateTimeInterface $to): int;
}
