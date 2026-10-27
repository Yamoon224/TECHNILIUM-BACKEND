<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Controllers;

use App\Modules\Catalog\Application\Contracts\VendorOfferGatewayInterface;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Data\OrderSummaryData;
use App\Modules\Payouts\Application\Contracts\VendorPayoutReaderInterface;
use App\Modules\Payouts\Application\Data\VendorPayoutData;
use App\Modules\Vendors\Http\Concerns\ResolvesActiveVendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Read-only vendor space (cahier §6.2): the vendor's own orders, payout
 * statements and a dashboard summary. Every query is scoped to the vendor
 * resolved from the authenticated user — never from the request — and other
 * modules are reached only through their public contracts.
 */
class VendorSpaceController extends Controller
{
    use ResolvesActiveVendor;

    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly VendorPayoutReaderInterface $payouts,
        private readonly VendorOfferGatewayInterface $offers,
    ) {}

    #[OA\Get(
        path: '/vendors/me/summary',
        summary: 'Tableau de bord du vendeur connecté (commandes, offres, reversements)',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [new OA\Response(response: 200, description: 'Synthèse')],
    )]
    public function summary(Request $request): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        $orders = $this->orders->forVendor($vendor->id);
        $payouts = $this->payouts->listFor($vendor->id);
        $offers = $this->offers->listOffersForVendor($vendor->id);

        $sum = fn (array $list, string $status) => array_sum(array_map(
            fn (VendorPayoutData $p) => $p->status === $status ? $p->netAmount : 0,
            $list,
        ));

        return response()->json([
            'company_name' => $vendor->company_name,
            'orders_by_status' => array_count_values(array_map(fn (OrderSummaryData $o) => $o->status, $orders)),
            'offers_by_status' => array_count_values(array_map(fn ($offer) => $offer->status, $offers)),
            'payout_pending_amount' => $sum($payouts, 'pending'),
            'payout_paid_amount' => $sum($payouts, 'paid'),
        ]);
    }

    #[OA\Get(
        path: '/vendors/me/orders',
        summary: 'Commandes contenant des articles du vendeur (ses lignes uniquement)',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [new OA\Response(response: 200, description: 'Commandes du vendeur')],
    )]
    public function orders(Request $request): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        return response()->json([
            'data' => array_map(fn (OrderSummaryData $order) => [
                'order_number' => $order->orderNumber,
                'status' => $order->status,
                'customer_name' => $order->customerName,
                'customer_phone' => $order->customerPhone,
                'delivery_address' => $order->deliveryAddress,
                'delivery_city' => $order->deliveryCity,
                'currency' => $order->currency,
                // The vendor's share only — not the whole (multi-vendor) order total.
                'vendor_subtotal_amount' => array_sum(array_map(fn ($l) => $l->lineTotalAmount, $order->lines)),
                'delivered_at' => $order->deliveredAt,
                'lines' => array_map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price_amount' => $line->unitPriceAmount,
                    'line_total_amount' => $line->lineTotalAmount,
                ], $order->lines),
            ], $this->orders->forVendor($vendor->id)),
        ]);
    }

    #[OA\Get(
        path: '/vendors/me/payouts',
        summary: 'Reversements du vendeur connecté',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [new OA\Response(response: 200, description: 'Reversements')],
    )]
    public function payouts(Request $request): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        return response()->json([
            'data' => array_map($this->payoutToArray(...), $this->payouts->listFor($vendor->id)),
        ]);
    }

    #[OA\Get(
        path: '/vendors/me/payouts/{payout}',
        summary: 'Relevé détaillé d\'un reversement du vendeur connecté',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [
            new OA\Response(response: 200, description: 'Relevé'),
            new OA\Response(response: 404, description: 'Introuvable (ou appartenant à un autre vendeur)'),
        ],
    )]
    public function payout(Request $request, int $payout): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        $data = $this->payouts->findFor($vendor->id, $payout);

        if ($data === null) {
            abort(404, 'Reversement introuvable.');
        }

        return response()->json($this->payoutToArray($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutToArray(VendorPayoutData $payout): array
    {
        return [
            'id' => $payout->id,
            'status' => $payout->status,
            'currency' => $payout->currency,
            'commission_rate_percent' => $payout->commissionRatePercent,
            'gross_amount' => $payout->grossAmount,
            'commission_amount' => $payout->commissionAmount,
            'net_amount' => $payout->netAmount,
            'payment_reference' => $payout->paymentReference,
            'paid_at' => $payout->paidAt,
            'created_at' => $payout->createdAt,
            'items' => $payout->items,
        ];
    }
}
