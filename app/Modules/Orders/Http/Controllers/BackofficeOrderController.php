<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Application\Actions\TransitionOrderStatusAction;
use App\Modules\Orders\Application\Actions\TransitionSubOrderAction;
use App\Modules\Orders\Domain\Exceptions\InvalidOrderTransitionException;
use App\Modules\Orders\Http\Requests\TransitionOrderStatusRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Staff-facing order management. Gated by auth:sanctum only for now - any
 * authenticated backoffice account can see and move every order. Scoping to
 * roles (opérateur commandes, administrateur - cahier §4) is a follow-up
 * once Identity grows a real permissions/admin module (§6.15); don't assume
 * this is the final access model.
 */
class BackofficeOrderController extends Controller
{
    public function __construct(
        private readonly TransitionOrderStatusAction $transitionStatus,
        private readonly TransitionSubOrderAction $transitionSubOrder,
    ) {}

    #[OA\Get(
        path: '/orders',
        summary: 'Liste des commandes pour le backoffice, filtrable par statut (cahier des charges §6.5)',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Commandes')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents')
            ->when(
                $request->string('status')->isNotEmpty(),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->string('channel')->isNotEmpty(),
                fn ($query) => $query->where('channel', $request->string('channel')->toString()),
            )
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        return OrderResource::collection($orders);
    }

    #[OA\Post(
        path: '/orders/{orderNumber}/transition',
        summary: 'Fait avancer le statut d\'une commande (pending -> confirmed -> shipped -> delivered, ou cancelled)',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'orderNumber', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['status'],
            properties: [new OA\Property(property: 'status', type: 'string', example: 'confirmed')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 404, description: 'Commande introuvable'),
            new OA\Response(response: 422, description: 'Transition non autorisée'),
        ],
    )]
    public function transition(string $orderNumber, TransitionOrderStatusRequest $request): OrderResource
    {
        $order = Order::query()->where('order_number', $orderNumber)->with('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents')->firstOrFail();

        try {
            $order = $this->transitionStatus->execute($order, $request->targetStatus());
        } catch (InvalidOrderTransitionException $exception) {
            abort(422, $exception->getMessage());
        }

        return new OrderResource($order);
    }

    #[OA\Post(
        path: '/orders/{orderNumber}/sub-orders/{subOrder}/transition',
        summary: "Fait avancer le statut d'une sous-commande (la part d'un vendeur) ; le statut de la commande en découle",
        security: [['sanctum' => []]],
        tags: ['Orders'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['status'],
            properties: [new OA\Property(property: 'status', type: 'string', example: 'delivered')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Commande mise à jour'),
            new OA\Response(response: 404, description: 'Commande ou sous-commande introuvable'),
            new OA\Response(response: 422, description: 'Transition non autorisée'),
        ],
    )]
    public function transitionSubOrder(string $orderNumber, int $subOrder, TransitionOrderStatusRequest $request): OrderResource
    {
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        // Scoped to the order in the URL: a sub-order id of another order is a 404.
        $target = $order->subOrders()->findOrFail($subOrder);

        try {
            $this->transitionSubOrder->execute($target, $request->targetStatus());
        } catch (InvalidOrderTransitionException $exception) {
            abort(422, $exception->getMessage());
        }

        return new OrderResource($order->refresh()->load('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents'));
    }
}
