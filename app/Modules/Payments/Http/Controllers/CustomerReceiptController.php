<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Payments\Application\RenderPaymentReceipt;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * A customer's own payment receipts. Ownership is the order's customer;
 * anything else — another customer's order, a guest order, an unknown number
 * or payment, a payment that was never received — answers 404, never 403.
 */
class CustomerReceiptController extends Controller
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly RenderPaymentReceipt $receipt,
    ) {}

    #[OA\Get(
        path: '/customer/orders/{orderNumber}/receipts',
        summary: "Reçus de paiement disponibles pour une commande du client connecté",
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Reçus'),
            new OA\Response(response: 404, description: 'Commande introuvable ou non rattachée au client'),
        ],
    )]
    public function forOrder(Request $request, string $orderNumber): JsonResponse
    {
        abort_unless($this->owns($request, $orderNumber), 404);

        $payments = Payment::query()
            ->where('order_number', $orderNumber)
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $payments->map(fn (Payment $payment) => [
            'id' => $payment->id,
            'number' => $this->receipt->number($payment),
            'method' => $payment->method->value,
            'amount_amount' => $payment->amount_amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ])->all()]);
    }

    #[OA\Get(
        path: '/customer/payments/{payment}/receipt',
        summary: "Télécharge le reçu d'un paiement du client connecté, dans la langue de sa commande",
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier PDF'),
            new OA\Response(response: 404, description: 'Introuvable ou non rattaché au client'),
        ],
    )]
    public function pdf(Request $request, Payment $payment): Response
    {
        $order = $this->orders->find($payment->order_number);

        abort_unless(
            $order !== null && $order->customerId !== null && $order->customerId === $request->user()->id && $this->receipt->isIssuable($payment),
            404,
        );

        return response($this->receipt->pdf($payment, $order->customerLocale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->receipt->filename($payment).'"',
        ]);
    }

    private function owns(Request $request, string $orderNumber): bool
    {
        $order = $this->orders->find($orderNumber);

        return $order !== null && $order->customerId !== null && $order->customerId === $request->user()->id;
    }
}
