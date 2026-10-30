<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Orders\Application\Contracts\OrderStatsInterface;
use App\Modules\Payments\Application\Contracts\PaymentStatsInterface;
use App\Modules\Payouts\Application\Contracts\PayoutStatsInterface;
use App\Modules\Returns\Application\Contracts\ReturnStatsInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the management reports (cahier §6.14) from each module's public
 * read-model contract — it never touches another module's tables or models.
 *
 * All amounts are reported in XOF minor units: multi-currency consolidation
 * is out of scope until exchange rates exist (cahier §6.15).
 */
final class ReportingService
{
    public function __construct(
        private readonly OrderStatsInterface $orders,
        private readonly PaymentStatsInterface $payments,
        private readonly PayoutStatsInterface $payouts,
        private readonly ReturnStatsInterface $returns,
        private readonly InventoryGatewayInterface $inventory,
    ) {}

    /** @var array<string, array<string, mixed>> summaries computed during this request, by period */
    private array $summaries = [];

    /**
     * The period summary; the dashboard blocks of one request share a single computation.
     *
     * @return array<string, mixed>
     */
    public function dashboard(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $key = $from->format('c').'|'.$to->format('c');

        return $this->summaries[$key] ??= $this->computeDashboard($from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeDashboard(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $salesByDay = $this->orders->salesByDay($from, $to);
        $revenue = array_sum(array_column($salesByDay, 'revenue'));
        $ordersCount = array_sum(array_column($salesByDay, 'orders'));

        $returnsByStatus = $this->returns->countsByStatus($from, $to);
        $returnsCount = array_sum($returnsByStatus);

        $lowStock = $this->inventory->listBelowThreshold((int) config('reporting.low_stock_threshold'));
        $collected = $this->payments->collectedBetween($from, $to);

        return [
            'currency' => 'XOF',
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'sales' => [
                'revenue' => $revenue,
                'orders' => $ordersCount,
                'average_basket' => $ordersCount > 0 ? intdiv($revenue, $ordersCount) : 0,
                'by_day' => $salesByDay,
            ],
            'orders_by_status' => $this->orders->ordersByStatus($from, $to),
            'top_products' => $this->orders->topProducts($from, $to, 5),
            'finance' => [
                'collected' => $collected['collected'],
                'collected_by_method' => $collected['by_method'],
                'refunded' => $this->payments->refundedBetween($from, $to),
                'commissions' => $this->payouts->commissionsBetween($from, $to),
                'paid_to_vendors' => $this->payouts->paidBetween($from, $to),
                'owed_to_vendors' => $this->payouts->owedToVendors(),
            ],
            'returns' => [
                'by_status' => $returnsByStatus,
                'count' => $returnsCount,
                // Share of orders that triggered a return in the period; 0 when there were no orders.
                'rate_percent' => $ordersCount > 0 ? round($returnsCount * 100 / $ordersCount, 1) : 0.0,
                'refunded_amount' => $this->returns->refundedBetween($from, $to),
            ],
            'alerts' => [
                'stalled_orders' => $this->orders->countStalledBefore(
                    now()->subDays((int) config('reporting.stalled_after_days')),
                ),
                'low_stock_count' => count($lowStock),
                'low_stock' => array_map(fn ($offer) => [
                    'offer_id' => $offer->offerId,
                    'product' => trim($offer->brand.' '.$offer->productName),
                    'sku' => $offer->sku,
                    'stock' => $offer->stockQuantity,
                ], array_slice($lowStock, 0, 10)),
            ],
        ];
    }

    /**
     * Revenue per seller, named (vendor_id null = Technilium's own stock).
     *
     * @return list<array{vendor_id: int|null, seller: string, orders: int, revenue: int}>
     */
    public function salesBySeller(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $rows = $this->orders->revenueBySeller($from, $to);
        $ids = array_values(array_filter(array_column($rows, 'vendor_id'), fn ($id) => $id !== null));

        // Vendors owns its table; names are read by table name, as Payouts does.
        $names = $ids === [] ? collect() : DB::table('vendors')->whereIn('id', $ids)->pluck('company_name', 'id');

        return array_map(fn (array $row) => [
            'vendor_id' => $row['vendor_id'],
            'seller' => $row['vendor_id'] === null ? 'Technilium' : (string) ($names[$row['vendor_id']] ?? 'Vendeur #'.$row['vendor_id']),
            'orders' => $row['orders'],
            'revenue' => $row['revenue'],
        ], $rows);
    }

    /**
     * @return list<array{date: string, orders: int, revenue: int}>
     */
    public function salesByDay(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->orders->salesByDay($from, $to);
    }
}
