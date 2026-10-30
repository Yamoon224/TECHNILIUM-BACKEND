<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Application\Contracts\InventoryGatewayInterface;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Application\Actions\RecordStockMovementAction;
use App\Modules\Inventory\Http\Requests\StoreStockMovementRequest;
use App\Modules\Inventory\Http\Resources\OfferStockResource;
use App\Modules\Inventory\Http\Resources\StockMovementResource;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Manual stock adjustments and audit trail (cahier §7 "intégrité des
 * stocks"). Gated by can:manage-stock - gestionnaire_stock or administrateur.
 */
class StockMovementController extends Controller
{
    public function __construct(
        private readonly RecordStockMovementAction $recordStockMovement,
        private readonly InventoryGatewayInterface $inventory,
    ) {}

    #[OA\Get(
        path: '/admin/stock/movements',
        summary: 'Historique des mouvements de stock, filtrable par offre',
        security: [['sanctum' => []]],
        tags: ['Inventory'],
        responses: [new OA\Response(response: 200, description: 'Mouvements')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = StockMovement::query()->orderByDesc('id');

        if ($request->filled('offer_id')) {
            $query->where('offer_id', $request->integer('offer_id'));
        }

        return StockMovementResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/stock/movements',
        summary: 'Enregistre un mouvement de stock manuel (réception, casse, correction d\'inventaire)',
        security: [['sanctum' => []]],
        tags: ['Inventory'],
        responses: [
            new OA\Response(response: 201, description: 'Mouvement enregistré'),
            new OA\Response(response: 422, description: 'Stock insuffisant ou erreur de validation'),
        ],
    )]
    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        try {
            $movement = $this->recordStockMovement->execute(
                $request->integer('offer_id'),
                $request->integer('quantity_delta'),
                $request->string('reason')->toString() ?: null,
                $request->user()?->id,
            );
        } catch (InsufficientStockException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/admin/stock/low-stock',
        summary: 'Liste les offres dont le stock est sous le seuil indiqué',
        security: [['sanctum' => []]],
        tags: ['Inventory'],
        responses: [new OA\Response(response: 200, description: 'Offres en stock faible')],
    )]
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $threshold = max(0, min($request->integer('threshold', 5), 1000));

        return OfferStockResource::collection($this->inventory->listBelowThreshold($threshold));
    }
}
