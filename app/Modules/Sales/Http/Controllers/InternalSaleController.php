<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Orders\Application\Data\CheckoutItemInput;
use App\Modules\Orders\Application\Data\DeliveryDetails;
use App\Modules\Orders\Application\Data\OrderSummaryData;
use App\Modules\Orders\Domain\Exceptions\CouponRejectedException;
use App\Modules\Orders\Domain\Exceptions\EmptyCartException;
use App\Modules\Orders\Domain\Exceptions\OfferUnavailableException;
use App\Modules\Payments\Application\Contracts\PaymentLookupInterface;
use App\Modules\Payments\Application\Data\CollectedPaymentData;
use App\Modules\Payments\Application\RenderPaymentReceipt;
use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Sales\Application\Actions\CancelInternalSaleAction;
use App\Modules\Sales\Application\Actions\RecordInternalSaleAction;
use App\Modules\Sales\Application\InternalSaleException;
use App\Modules\Sales\Http\Requests\CancelInternalSaleRequest;
use App\Modules\Sales\Http\Requests\RecordInternalSaleRequest;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/** Counter sales, gated by can:manage-internal-sales. */
class InternalSaleController extends Controller
{
    public function __construct(
        private readonly RecordInternalSaleAction $recordSale,
        private readonly OrderLookupGatewayInterface $orders,
        private readonly PaymentLookupInterface $payments,
    ) {}

    #[OA\Get(
        path: '/admin/internal-sales',
        summary: 'Dernières ventes internes avec leur reçu',
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [new OA\Response(response: 200, description: 'Ventes internes')],
    )]
    public function index(Request $request): JsonResponse
    {
        $sales = $this->orders->recentInternalSales(max(1, min($request->integer('limit', 50), 200)));
        $numbers = array_map(fn (OrderSummaryData $sale) => $sale->orderNumber, $sales);
        $payments = $this->payments->collectedForOrders($numbers);
        $records = InternalSale::query()->whereIn('order_number', $numbers)->get()->keyBy('order_number');

        return response()->json([
            'data' => array_map(fn (OrderSummaryData $sale) => $this->present($sale, $payments[$sale->orderNumber] ?? null, $records[$sale->orderNumber] ?? null), $sales),
        ]);
    }

    #[OA\Post(
        path: '/admin/internal-sales',
        summary: 'Enregistre une vente au comptoir : commande livrée et payée sur-le-champ, reçu de paiement délivré',
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [
            new OA\Response(response: 201, description: 'Vente enregistrée'),
            new OA\Response(response: 422, description: 'Panier invalide, stock insuffisant, offre de vendeur tiers ou espèces insuffisantes'),
        ],
    )]
    public function store(RecordInternalSaleRequest $request): JsonResponse
    {
        $items = array_map(
            fn (array $line) => new CheckoutItemInput((int) $line['offer_id'], (int) $line['quantity']),
            $request->array('items'),
        );

        $customer = new DeliveryDetails(
            customerName: $request->string('customer_name')->trim()->toString() ?: 'Client comptoir',
            customerPhone: $request->string('customer_phone')->trim()->toString(),
            address: 'Vente en boutique',
            city: null,
            notes: $request->string('notes')->toString() ?: null,
            customerEmail: $request->string('customer_email')->toString() ?: null,
            locale: $request->input('locale') === 'en' ? 'en' : 'fr',
        );

        try {
            $result = $this->recordSale->execute(
                $items,
                $customer,
                PaymentMethod::from($request->string('method')->toString()),
                (int) $request->user()->id,
                $request->string('provider')->toString() ?: null,
                $request->string('provider_reference')->toString() ?: null,
                $request->filled('tendered_amount') ? $request->integer('tendered_amount') : null,
                $request->filled('coupon_code') ? $request->string('coupon_code')->toString() : null,
            );
        } catch (EmptyCartException|OfferUnavailableException|CouponRejectedException|InternalSaleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $orderNumber = $result['order']->order_number;
        $sale = $this->orders->find($orderNumber);
        $collected = $this->payments->collectedForOrders([$orderNumber])[$orderNumber] ?? null;

        return response()->json($this->present($sale, $collected, InternalSale::query()->where('order_number', $orderNumber)->first()), 201);
    }

    #[OA\Get(
        path: '/admin/internal-sales/{orderNumber}/receipt',
        summary: "Reçu de paiement (PDF) d'une vente interne",
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier PDF'),
            new OA\Response(response: 404, description: 'Vente interne introuvable'),
        ],
    )]
    public function receipt(Request $request, string $orderNumber): Response|JsonResponse
    {
        $sale = $this->orders->find($orderNumber);
        $receipt = $sale?->channel === 'internal'
            ? $this->payments->receiptPdfForOrder($orderNumber, (string) $request->query('lang', 'fr'), RenderPaymentReceipt::normalizeFormat($request->query('format')))
            : null;

        if ($receipt === null) {
            return response()->json(['message' => 'Vente interne introuvable.'], 404);
        }

        return response($receipt['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt['filename'].'"',
        ]);
    }

    #[OA\Post(
        path: '/admin/internal-sales/{orderNumber}/cancel',
        summary: "Annule une vente interne : retour en stock, avoir, remboursement intégral et écritures inverses",
        security: [['sanctum' => []]],
        tags: ['Sales'],
        responses: [
            new OA\Response(response: 200, description: 'Vente annulée'),
            new OA\Response(response: 404, description: 'Vente interne introuvable'),
            new OA\Response(response: 422, description: 'Déjà annulée ou hors délai de retour'),
        ],
    )]
    public function cancel(CancelInternalSaleRequest $request, string $orderNumber, CancelInternalSaleAction $cancel): JsonResponse
    {
        if (! InternalSale::query()->where('order_number', $orderNumber)->exists()) {
            return response()->json(['message' => 'Vente interne introuvable.'], 404);
        }

        try {
            $record = $cancel->execute($orderNumber, $request->string('reason')->toString(), (int) $request->user()->id);
        } catch (InternalSaleException|ReturnException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $sale = $this->orders->find($orderNumber);

        return response()->json($this->present($sale, $this->payments->collectedForOrders([$orderNumber])[$orderNumber] ?? null, $record));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(OrderSummaryData $sale, ?CollectedPaymentData $payment, ?InternalSale $record = null): array
    {
        return [
            'order_number' => $sale->orderNumber,
            'status' => $sale->status,
            'customer_name' => $sale->customerName,
            'customer_phone' => $sale->customerPhone,
            'currency' => $sale->currency,
            'total_amount' => $sale->subtotalAmount,
            'discount_amount' => $sale->discountAmount,
            'coupon_code' => $sale->couponCode,
            'created_at' => $sale->deliveredAt,
            'cancelled_at' => $record?->cancelled_at?->toIso8601String(),
            'cancel_reason' => $record?->cancel_reason,
            'lines' => array_map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price_amount' => $line->unitPriceAmount,
                'line_total_amount' => $line->lineTotalAmount,
            ], $sale->lines),
            'payment' => $payment === null ? null : [
                'id' => $payment->paymentId,
                'receipt_number' => $payment->receiptNumber,
                'method' => $payment->method,
                'provider' => $payment->provider,
                'amount_amount' => $payment->amountAmount,
                'tendered_amount' => $payment->tenderedAmount,
                'change_amount' => $payment->tenderedAmount === null ? null : max(0, $payment->tenderedAmount - $payment->amountAmount),
                'paid_at' => $payment->paidAt,
            ],
        ];
    }
}
