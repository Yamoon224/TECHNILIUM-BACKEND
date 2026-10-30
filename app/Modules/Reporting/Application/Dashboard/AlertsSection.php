<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Dashboard;

use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/** Direction: what needs a decision today (stalled orders, stock running out). */
final class AlertsSection extends ManagementSection
{
    public function key(): string
    {
        return 'alerts';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $alerts = $this->summary($from, $to)['alerts'];

        return Widgets::section(
            'Alertes',
            'Alerts',
            '/rapports',
            kpis: [
                Widgets::kpi('stalled_orders', 'Commandes bloquées', 'Stalled orders', (int) $alerts['stalled_orders'], Widgets::NUMBER, $alerts['stalled_orders'] > 0 ? Widgets::BAD : Widgets::GOOD),
                Widgets::kpi('low_stock', 'Produits en stock bas', 'Low-stock products', (int) $alerts['low_stock_count'], Widgets::NUMBER, $alerts['low_stock_count'] > 0 ? Widgets::WARN : Widgets::GOOD),
            ],
            tables: [
                Widgets::table('low_stock_list', 'Stock bas', 'Low stock', [['Produit', 'Product'], ['SKU', 'SKU'], ['Stock', 'Stock']], array_map(
                    fn (array $row) => [$row['product'], $row['sku'], (int) $row['stock']],
                    $alerts['low_stock'],
                )),
            ],
        );
    }
}
