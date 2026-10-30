<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Dashboard;

use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/** Finance: what was collected, refunded, earned in commissions and still owed to vendors. */
final class FinanceSection extends ManagementSection
{
    public function key(): string
    {
        return 'finance';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $finance = $this->summary($from, $to)['finance'];

        $methods = [];
        foreach ($finance['collected_by_method'] as $method => $amount) {
            $methods[] = ['label' => ucfirst(str_replace('_', ' ', (string) $method)), 'value' => (int) $amount];
        }

        return Widgets::section(
            'Finance',
            'Finance',
            '/rapports',
            kpis: [
                Widgets::kpi('collected', 'Encaissé', 'Collected', (int) $finance['collected'], Widgets::MONEY, Widgets::GOOD),
                Widgets::kpi('refunded', 'Remboursé', 'Refunded', (int) $finance['refunded'], Widgets::MONEY),
                Widgets::kpi('commissions', 'Commissions', 'Commissions', (int) $finance['commissions'], Widgets::MONEY),
                Widgets::kpi('owed_to_vendors', 'Dû aux vendeurs', 'Owed to vendors', (int) $finance['owed_to_vendors'], Widgets::MONEY, $finance['owed_to_vendors'] > 0 ? Widgets::WARN : null),
            ],
            charts: [
                Widgets::chart('collected_by_method', 'Encaissé par méthode', 'Collected by method', 'donut', $methods, Widgets::MONEY),
            ],
        );
    }
}
