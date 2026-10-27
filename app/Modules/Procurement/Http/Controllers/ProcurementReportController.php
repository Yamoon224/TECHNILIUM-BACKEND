<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Procurement\Infrastructure\Models\OfferCost;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/** Read-only procurement figures: what is owed to suppliers, and what the stock is worth. */
class ProcurementReportController extends Controller
{
    public function __construct(private readonly InventoryGatewayInterface $inventory) {}

    #[OA\Get(
        path: '/admin/procurement/payables',
        summary: 'Dettes fournisseurs : total dû, dont échu, et détail par fournisseur',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Dettes')],
    )]
    public function payables(): JsonResponse
    {
        $open = SupplierInvoice::query()->with('supplier')->whereColumn('paid_amount', '<', 'amount')->get();

        $bySupplier = $open->groupBy('supplier_id')->map(fn ($invoices) => [
            'supplier_id' => $invoices->first()->supplier_id,
            'supplier_name' => $invoices->first()->supplier->name,
            'outstanding' => $invoices->sum(fn (SupplierInvoice $i) => $i->outstanding()),
            'overdue' => $invoices->filter(fn (SupplierInvoice $i) => $i->isOverdue())->sum(fn (SupplierInvoice $i) => $i->outstanding()),
            'invoices' => $invoices->count(),
        ])->sortByDesc('outstanding')->values();

        return response()->json([
            'currency' => 'XOF',
            'total_outstanding' => (int) $bySupplier->sum('outstanding'),
            'total_overdue' => (int) $bySupplier->sum('overdue'),
            'by_supplier' => $bySupplier->all(),
        ]);
    }

    #[OA\Get(
        path: '/admin/procurement/stock-valuation',
        summary: 'Valorisation du stock acheté au coût moyen pondéré (CMP), par offre',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Valorisation')],
    )]
    public function stockValuation(): JsonResponse
    {
        $rows = OfferCost::query()->orderBy('offer_id')->limit(500)->get()->map(function (OfferCost $cost) {
            $offer = $this->inventory->find($cost->offer_id);
            $stock = $offer?->stockQuantity ?? 0;

            return [
                'offer_id' => $cost->offer_id,
                'product' => $offer ? trim($offer->brand.' '.$offer->productName) : 'Offre #'.$cost->offer_id,
                'sku' => $offer?->sku,
                'stock' => $stock,
                'average_cost' => $cost->average_cost_amount,
                'value' => $stock * $cost->average_cost_amount,
            ];
        });

        return response()->json([
            'currency' => 'XOF',
            'total_value' => (int) $rows->sum('value'),
            'offers' => $rows->all(),
        ]);
    }
}
