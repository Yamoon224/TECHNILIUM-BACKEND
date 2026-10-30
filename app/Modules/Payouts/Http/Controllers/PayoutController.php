<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Http\Controllers;

use App\Modules\Payouts\Application\Actions\CalculatePayoutAction;
use App\Modules\Payouts\Application\Actions\MarkPayoutPaidAction;
use App\Modules\Payouts\Domain\Exceptions\PayoutException;
use App\Modules\Payouts\Http\Requests\CalculatePayoutRequest;
use App\Modules\Payouts\Http\Requests\MarkPayoutPaidRequest;
use App\Modules\Payouts\Http\Resources\PayoutResource;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Vendor payouts (cahier §6.8). Gated by can:manage-payouts - comptable or
 * administrateur.
 */
class PayoutController extends Controller
{
    public function __construct(
        private readonly CalculatePayoutAction $calculatePayout,
        private readonly MarkPayoutPaidAction $markPaid,
    ) {}

    #[OA\Get(
        path: '/admin/payouts',
        summary: 'Liste les reversements, filtrables par vendeur ou statut',
        security: [['sanctum' => []]],
        tags: ['Payouts'],
        responses: [new OA\Response(response: 200, description: 'Reversements')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = Payout::query()->orderByDesc('id');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return PayoutResource::collection($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/admin/payouts/{payout}',
        summary: 'Relevé détaillé d\'un reversement (commandes incluses)',
        security: [['sanctum' => []]],
        tags: ['Payouts'],
        responses: [
            new OA\Response(response: 200, description: 'Reversement'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ],
    )]
    public function show(Payout $payout): PayoutResource
    {
        return new PayoutResource($payout->load('items'));
    }

    #[OA\Post(
        path: '/admin/payouts',
        summary: 'Calcule le reversement d\'un vendeur (ventes livrées, hors délai de retour, moins commission)',
        security: [['sanctum' => []]],
        tags: ['Payouts'],
        responses: [
            new OA\Response(response: 201, description: 'Reversement calculé'),
            new OA\Response(response: 422, description: 'Rien d\'exigible ou vendeur inconnu'),
        ],
    )]
    public function store(CalculatePayoutRequest $request): PayoutResource|JsonResponse
    {
        try {
            $payout = $this->calculatePayout->execute($request->integer('vendor_id'), $request->user()?->id);
        } catch (PayoutException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/payouts/{payout}/pay',
        summary: 'Enregistre l\'exécution du virement ou du mobile money',
        security: [['sanctum' => []]],
        tags: ['Payouts'],
        responses: [
            new OA\Response(response: 200, description: 'Reversement payé'),
            new OA\Response(response: 422, description: 'Déjà payé ou référence manquante'),
        ],
    )]
    public function pay(MarkPayoutPaidRequest $request, Payout $payout): PayoutResource|JsonResponse
    {
        try {
            $paid = $this->markPaid->execute($payout->id, $request->string('payment_reference')->toString());
        } catch (PayoutException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new PayoutResource($paid);
    }
}
