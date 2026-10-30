<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Dashboard;

use App\Modules\Reporting\Application\ReportingService;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/**
 * Base of the management blocks (Direction / Finance): they all read the same
 * period summary, which the service keeps for the request.
 */
abstract class ManagementSection implements DashboardSection
{
    public function __construct(protected readonly ReportingService $reports) {}

    public function ability(): string
    {
        return 'view-reports';
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->reports->dashboard($from, $to);
    }
}
