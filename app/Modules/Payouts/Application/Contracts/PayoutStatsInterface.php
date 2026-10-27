<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Contracts;

use DateTimeInterface;

/** Payouts' public read-model for Reporting (commissions, vendor debt). */
interface PayoutStatsInterface
{
    /** What Technilium still owes vendors: net of payouts calculated but not yet paid. */
    public function owedToVendors(): int;

    /** Commission earned on payouts calculated in [from, to]. */
    public function commissionsBetween(DateTimeInterface $from, DateTimeInterface $to): int;

    /** Net amounts actually paid to vendors in [from, to]. */
    public function paidBetween(DateTimeInterface $from, DateTimeInterface $to): int;
}
