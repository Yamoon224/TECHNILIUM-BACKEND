<?php

declare(strict_types=1);

namespace App\Modules\Shared\Dashboard;

use Carbon\CarbonInterface;

/**
 * A block of the back-office home page, supplied by the module that owns the data.
 *
 * A module registers its sections in its service provider with
 * `$this->app->tag([MySection::class], DashboardSection::class)`; the Reporting
 * module collects the tagged sections and shows each one to the accounts that
 * hold its ability (so every profile, built-in or custom, gets the blocks of the
 * features it may use). A section reads only its own module's data and returns
 * the generic shape built with {@see Widgets}.
 */
interface DashboardSection
{
    /** Stable identifier, e.g. "orders". */
    public function key(): string;

    /** The feature that unlocks it (an AccessCatalogue key, e.g. "manage-orders"). */
    public function ability(): string;

    /**
     * @return array<string, mixed> The array returned by {@see Widgets::section()}
     */
    public function build(CarbonInterface $from, CarbonInterface $to): array;
}
