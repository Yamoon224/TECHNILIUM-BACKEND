<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Dashboard;

use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/** Direction: sales of the period, how they split, and what sells. */
final class SalesSection extends ManagementSection
{
    /** @var array<string, array{0: string, 1: string}> */
    private const STATUS = [
        'pending' => ['En attente', 'Pending'],
        'confirmed' => ['Confirmées', 'Confirmed'],
        'shipped' => ['Expédiées', 'Shipped'],
        'delivered' => ['Livrées', 'Delivered'],
        'cancelled' => ['Annulées', 'Cancelled'],
    ];

    public function key(): string
    {
        return 'sales';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $summary = $this->summary($from, $to);
        $sales = $summary['sales'];

        $statuses = [];
        foreach ($summary['orders_by_status'] as $status => $count) {
            [$fr, $en] = self::STATUS[$status] ?? [ucfirst(str_replace('_', ' ', (string) $status)), ucfirst(str_replace('_', ' ', (string) $status))];
            $statuses[] = ['label' => $fr, 'label_en' => $en, 'value' => (int) $count];
        }

        return Widgets::section(
            'Ventes',
            'Sales',
            '/rapports',
            kpis: [
                Widgets::kpi('revenue', "Chiffre d'affaires", 'Revenue', (int) $sales['revenue'], Widgets::MONEY),
                Widgets::kpi('orders', 'Commandes', 'Orders', (int) $sales['orders']),
                Widgets::kpi('average_basket', 'Panier moyen', 'Average basket', (int) $sales['average_basket'], Widgets::MONEY),
                Widgets::kpi('returns_rate', 'Taux de retours', 'Return rate', (float) $summary['returns']['rate_percent'], Widgets::PERCENT, $summary['returns']['rate_percent'] > 10 ? Widgets::WARN : null),
            ],
            charts: [
                Widgets::chart('sales_by_day', "Chiffre d'affaires par jour", 'Revenue per day', 'line', array_map(
                    fn (array $day) => ['label' => date('d/m', (int) strtotime($day['date'])), 'value' => (int) $day['revenue']],
                    $sales['by_day'],
                ), Widgets::MONEY),
                Widgets::chart('orders_by_status', 'Commandes par statut', 'Orders by status', 'donut', $statuses),
            ],
            tables: [
                Widgets::table('top_products', 'Produits les plus vendus', 'Top products', [['Produit', 'Product'], ['Qté', 'Qty'], ["Chiffre d'affaires", 'Revenue']], array_map(
                    fn (array $row) => [$row['product'], (int) $row['quantity'], (int) $row['revenue']],
                    $summary['top_products'],
                )),
            ],
        );
    }
}
