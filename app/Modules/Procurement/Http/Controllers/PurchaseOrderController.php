<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers;

use App\Modules\Procurement\Application\Actions\AddLandedCostAction;
use App\Modules\Procurement\Application\Actions\ChangePurchaseOrderStatusAction;
use App\Modules\Procurement\Application\Actions\CreatePurchaseOrderAction;
use App\Modules\Procurement\Application\Actions\ReceiveGoodsAction;
use App\Modules\Procurement\Domain\Exceptions\ProcurementException;
use App\Modules\Procurement\Http\Requests\LandedCostRequest;
use App\Modules\Procurement\Http\Requests\ReceiveGoodsRequest;
use App\Modules\Procurement\Http\Requests\StorePurchaseOrderRequest;
use App\Modules\Procurement\Http\Resources\PurchaseOrderResource;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/** Purchase orders, landed costs and goods receipts (cahier §6.4). */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly CreatePurchaseOrderAction $create,
        private readonly ChangePurchaseOrderStatusAction $status,
        private readonly AddLandedCostAction $addLandedCost,
        private readonly ReceiveGoodsAction $receive,
    ) {}

    #[OA\Get(
        path: '/admin/purchase-orders',
        summary: 'Liste les bons de commande, filtrables par statut ou fournisseur',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Bons de commande')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::query()->with('supplier', 'lines', 'landedCosts')->orderByDesc('id');

        foreach (['status', 'supplier_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return PurchaseOrderResource::collection($query->paginate(max(1, min($request->integer('per_page', 30), 100))));
    }

    #[OA\Post(
        path: '/admin/purchase-orders',
        summary: 'Crée un bon de commande (brouillon) sur du stock propre de Technilium',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 201, description: 'Bon créé'), new OA\Response(response: 422, description: 'Offre non achetable ou données invalides')],
    )]
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->create->execute(
                $request->integer('supplier_id'),
                array_map(fn (array $line) => [
                    'offer_id' => (int) $line['offer_id'],
                    'quantity' => (int) $line['quantity'],
                    'unit_cost_amount' => (int) $line['unit_cost_amount'],
                ], $request->array('lines')),
                $request->filled('expected_at') ? $request->string('expected_at')->toString() : null,
                $request->filled('notes') ? $request->string('notes')->toString() : null,
                $request->user()?->id,
            );
        } catch (ProcurementException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new PurchaseOrderResource($order))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/admin/purchase-orders/{purchaseOrder}',
        summary: 'Un bon de commande avec ses lignes, frais d\'approche et réceptions',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Bon'), new OA\Response(response: 404, description: 'Introuvable')],
    )]
    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder->load('supplier', 'lines', 'landedCosts', 'receipts.lines'));
    }

    #[OA\Post(
        path: '/admin/purchase-orders/{purchaseOrder}/place',
        summary: 'Envoie le bon au fournisseur (brouillon → commandé)',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Bon commandé'), new OA\Response(response: 422, description: 'Transition interdite')],
    )]
    public function place(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        return $this->run(fn () => $this->status->place($purchaseOrder));
    }

    #[OA\Post(
        path: '/admin/purchase-orders/{purchaseOrder}/cancel',
        summary: 'Annule un bon, tant que rien n\'a été reçu',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 200, description: 'Bon annulé'), new OA\Response(response: 422, description: 'Déjà reçu en partie ou clos')],
    )]
    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        return $this->run(fn () => $this->status->cancel($purchaseOrder));
    }

    #[OA\Post(
        path: '/admin/purchase-orders/{purchaseOrder}/landed-costs',
        summary: 'Ajoute un frais d\'approche (transport, douane, manutention), réparti sur les marchandises reçues',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [new OA\Response(response: 201, description: 'Frais ajouté'), new OA\Response(response: 422, description: 'Bon déjà soldé ou annulé')],
    )]
    public function landedCost(LandedCostRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        return $this->run(function () use ($request, $purchaseOrder) {
            $this->addLandedCost->execute($purchaseOrder, $request->string('label')->toString(), $request->integer('amount'));

            return $purchaseOrder->refresh()->load('supplier', 'lines', 'landedCosts');
        }, 201);
    }

    #[OA\Post(
        path: '/admin/purchase-orders/{purchaseOrder}/receipts',
        summary: 'Réceptionne des marchandises (acceptées / refusées au contrôle) : entrée en stock, coût moyen et écritures',
        security: [['sanctum' => []]],
        tags: ['Procurement'],
        responses: [
            new OA\Response(response: 201, description: 'Réception enregistrée'),
            new OA\Response(response: 422, description: 'Quantité supérieure au reste à recevoir, ligne étrangère ou bon non réceptionnable'),
        ],
    )]
    public function receive(ReceiveGoodsRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        return $this->run(function () use ($request, $purchaseOrder) {
            $this->receive->execute(
                $purchaseOrder,
                array_map(fn (array $row) => [
                    'line_id' => (int) $row['line_id'],
                    'accepted' => (int) $row['accepted'],
                    'rejected' => (int) ($row['rejected'] ?? 0),
                ], $request->array('lines')),
                $request->filled('notes') ? $request->string('notes')->toString() : null,
                $request->user()?->id,
            );

            return $purchaseOrder->refresh()->load('supplier', 'lines', 'landedCosts', 'receipts.lines');
        }, 201);
    }

    private function run(callable $work, int $status = 200): PurchaseOrderResource|JsonResponse
    {
        try {
            $result = $work();
        } catch (ProcurementException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $resource = new PurchaseOrderResource($result);

        return $status === 200 ? $resource : $resource->response()->setStatusCode($status);
    }
}
