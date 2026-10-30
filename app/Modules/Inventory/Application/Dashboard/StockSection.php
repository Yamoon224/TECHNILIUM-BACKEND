<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Dashboard;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/**
 * Stock block of the back-office home page. Stock levels come from Catalog's
 * public contract; movements are Inventory's own. No stock valuation: Inventory
 * holds no cost data.
 */
final class StockSection implements DashboardSection
{
    public function __construct(private readonly InventoryGatewayInterface $inventory) {}

    public function key(): string
    {
        return 'stock';
    }

    public function ability(): string
    {
        return 'manage-stock';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $threshold = (int) config('reporting.low_stock_threshold');
        $offers = $this->inventory->listBelowThreshold($threshold);

        $outOfStock = count(array_filter($offers, fn ($o) => $o->stockQuantity <= 0));
        $low = count($offers) - $outOfStock;

        $movements = StockMovement::query()->whereBetween('created_at', [$from, $to]);
        $total = (clone $movements)->count();
        $in = (clone $movements)->where('quantity_delta', '>', 0)->count();
        $out = (clone $movements)->where('quantity_delta', '<', 0)->count();

        $rows = array_map(fn ($o) => [
            trim($o->brand.' '.$o->productName),
            $o->sku,
            $o->stockQuantity,
        ], array_slice($offers, 0, 10));

        return Widgets::section(
            'Stock',
            'Stock',
            '/stock',
            [
                Widgets::kpi('out_of_stock', 'Offres en rupture', 'Out-of-stock offers', $outOfStock, Widgets::NUMBER, $outOfStock > 0 ? Widgets::BAD : null),
                Widgets::kpi('low_stock', 'Offres sous le seuil', 'Offers below threshold', $low, Widgets::NUMBER, $low > 0 ? Widgets::WARN : null),
                Widgets::kpi('movements', 'Mouvements de la période', 'Movements in the period', $total),
            ],
            [
                Widgets::chart('movements_by_type', 'Mouvements par type', 'Movements by type', 'bar', [
                    ['label' => 'Entrées', 'label_en' => 'Inbound', 'value' => $in],
                    ['label' => 'Sorties', 'label_en' => 'Outbound', 'value' => $out],
                ]),
            ],
            [
                Widgets::table('low_stock_offers', 'Offres sous le seuil de stock', 'Offers below the stock threshold', [
                    ['Produit', 'Product'],
                    ['SKU', 'SKU'],
                    ['Stock', 'Stock'],
                ], $rows),
            ],
        );
    }
}
