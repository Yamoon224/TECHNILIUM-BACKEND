<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Application\Actions\RefundPaymentAction;
use App\Modules\Payments\Application\Actions\TransitionPaymentStatusAction;
use App\Modules\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Payments\Domain\Exceptions\InvalidRefundException;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Http\Requests\RefundPaymentRequest;
use App\Modules\Payments\Http\Requests\StorePaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Payment records and manual mobile-money reconciliation (cahier §5.2).
 * Gated by can:manage-payments — comptable or administrateur.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly TransitionPaymentStatusAction $transitionPaymentStatus,
        private readonly RefundPaymentAction $refundPayment,
    ) {}

    #[OA\Get(
        path: '/admin/payments',
        summary: 'Liste les paiements, filtrables par commande ou statut',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [new OA\Response(response: 200, description: 'Paiements')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = Payment::query()->orderByDesc('id');

        if ($request->filled('order_number')) {
            $query->where('order_number', $request->string('order_number')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return PaymentResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/payments',
        summary: 'Enregistre un paiement (en attente de confirmation)',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 201, description: 'Paiement créé'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = Payment::query()->create([
            'order_number' => $request->string('order_number')->toString(),
            'method' => $request->string('method')->toString(),
            'amount_amount' => $request->integer('amount_amount'),
            'currency' => $request->string('currency')->toString() ?: 'XOF',
            'provider' => $request->string('provider')->toString() ?: null,
            'provider_reference' => $request->string('provider_reference')->toString() ?: null,
            'created_by' => $request->user()?->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/payments/{payment}/confirm',
        summary: 'Confirme un paiement en attente (ex. référence mobile money vérifiée)',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Paiement confirmé'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function confirm(Payment $payment): PaymentResource|JsonResponse
    {
        return $this->transition($payment, PaymentStatus::Completed);
    }

    #[OA\Post(
        path: '/admin/payments/{payment}/fail',
        summary: 'Marque un paiement en attente comme échoué',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Paiement marqué échoué'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function fail(Payment $payment): PaymentResource|JsonResponse
    {
        return $this->transition($payment, PaymentStatus::Failed);
    }

    #[OA\Post(
        path: '/admin/payments/{payment}/refund',
        summary: 'Rembourse un paiement confirmé, en totalité ou en partie (montant optionnel)',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Paiement remboursé'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function refund(RefundPaymentRequest $request, Payment $payment): PaymentResource|JsonResponse
    {
        try {
            $refunded = $this->refundPayment->execute(
                $payment,
                $request->filled('amount') ? $request->integer('amount') : null,
                $request->filled('reason') ? $request->string('reason')->toString() : null,
                $request->user()?->id,
            );
        } catch (InvalidRefundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new PaymentResource($refunded);
    }

    private function transition(Payment $payment, PaymentStatus $target): PaymentResource|JsonResponse
    {
        try {
            $payment = $this->transitionPaymentStatus->execute($payment, $target);
        } catch (InvalidPaymentTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new PaymentResource($payment);
    }
}
