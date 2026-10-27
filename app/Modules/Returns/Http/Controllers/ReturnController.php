<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Controllers;

use App\Modules\Returns\Application\Actions\OpenReturnAction;
use App\Modules\Returns\Application\Actions\TransitionReturnAction;
use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Http\Requests\DecideReturnRequest;
use App\Modules\Returns\Http\Requests\OpenReturnRequest;
use App\Modules\Returns\Http\Resources\ReturnResource;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Returns / RMA back-office (cahier §6.11). Gated by can:manage-returns —
 * support or administrateur.
 */
class ReturnController extends Controller
{
    public function __construct(
        private readonly OpenReturnAction $openReturn,
        private readonly TransitionReturnAction $transition,
    ) {}

    #[OA\Get(
        path: '/admin/returns',
        summary: 'Liste les dossiers de retour, filtrables par commande ou statut',
        security: [['sanctum' => []]],
        tags: ['Returns'],
        responses: [new OA\Response(response: 200, description: 'Retours')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = ReturnRequest::query()->orderByDesc('id');

        if ($request->filled('order_number')) {
            $query->where('order_number', $request->string('order_number')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return ReturnResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/returns',
        summary: 'Ouvre un retour sur une commande livrée, dans le délai de retour',
        security: [['sanctum' => []]],
        tags: ['Returns'],
        responses: [
            new OA\Response(response: 201, description: 'Retour demandé'),
            new OA\Response(response: 422, description: 'Commande non livrée, délai dépassé ou montant trop élevé'),
        ],
    )]
    public function store(OpenReturnRequest $request): ReturnResource|JsonResponse
    {
        try {
            $return = $this->openReturn->execute(
                $request->string('order_number')->toString(),
                $request->string('reason')->toString(),
                $request->filled('amount') ? $request->integer('amount') : null,
                $request->user()?->id,
                $request->filled('items') ? $request->input('items') : null,
            );
        } catch (ReturnException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new ReturnResource($return))->response()->setStatusCode(201);
    }

    #[OA\Post(path: '/admin/returns/{return}/accept', summary: 'Accepte la demande de retour', security: [['sanctum' => []]], tags: ['Returns'], responses: [new OA\Response(response: 200, description: 'Retour accepté'), new OA\Response(response: 422, description: 'Transition impossible')])]
    public function accept(DecideReturnRequest $request, ReturnRequest $return): ReturnResource|JsonResponse
    {
        return $this->move($request, $return, ReturnStatus::Accepted);
    }

    #[OA\Post(path: '/admin/returns/{return}/reject', summary: 'Refuse le retour (avant acceptation ou après contrôle)', security: [['sanctum' => []]], tags: ['Returns'], responses: [new OA\Response(response: 200, description: 'Retour refusé'), new OA\Response(response: 422, description: 'Transition impossible')])]
    public function reject(DecideReturnRequest $request, ReturnRequest $return): ReturnResource|JsonResponse
    {
        return $this->move($request, $return, ReturnStatus::Rejected);
    }

    #[OA\Post(path: '/admin/returns/{return}/receive', summary: 'Enregistre la réception et le contrôle du colis', security: [['sanctum' => []]], tags: ['Returns'], responses: [new OA\Response(response: 200, description: 'Retour reçu'), new OA\Response(response: 422, description: 'Transition impossible')])]
    public function receive(DecideReturnRequest $request, ReturnRequest $return): ReturnResource|JsonResponse
    {
        return $this->move($request, $return, ReturnStatus::Received);
    }

    #[OA\Post(
        path: '/admin/returns/{return}/refund',
        summary: 'Rembourse le retour (total ou partiel) et émet l\'avoir correspondant',
        security: [['sanctum' => []]],
        tags: ['Returns'],
        responses: [
            new OA\Response(response: 200, description: 'Retour remboursé'),
            new OA\Response(response: 422, description: 'Transition impossible ou montant trop élevé'),
        ],
    )]
    public function refund(DecideReturnRequest $request, ReturnRequest $return): ReturnResource|JsonResponse
    {
        return $this->move($request, $return, ReturnStatus::Refunded);
    }

    private function move(DecideReturnRequest $request, ReturnRequest $return, ReturnStatus $target): ReturnResource|JsonResponse
    {
        try {
            $updated = $this->transition->execute(
                $return,
                $target,
                $request->filled('note') ? $request->string('note')->toString() : null,
                $request->filled('amount') ? $request->integer('amount') : null,
                $request->boolean('restock'),
            );
        } catch (ReturnException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new ReturnResource($updated);
    }
}
