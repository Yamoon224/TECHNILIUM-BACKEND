<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Application\Actions\PlaceOrderAction;
use App\Modules\Orders\Application\Data\CheckoutItemInput;
use App\Modules\Orders\Application\Data\DeliveryDetails;
use App\Modules\Orders\Domain\Exceptions\CouponRejectedException;
use App\Modules\Orders\Domain\Exceptions\EmptyCartException;
use App\Modules\Orders\Domain\Exceptions\OfferUnavailableException;
use App\Modules\Orders\Http\Requests\PlaceOrderRequest;
use App\Modules\Orders\Http\Requests\ShowOrderRequest;
use App\Modules\Orders\Http\Resources\CustomerOrderResource;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PlaceOrderAction $placeOrder,
    ) {}

    #[OA\Post(
        path: '/orders',
        summary: 'Passe une commande en paiement à la livraison (cahier des charges §5.4, §5.5)',
        tags: ['Orders'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items', 'customer_name', 'customer_phone', 'delivery_address'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'offer_id', type: 'integer'),
                        new OA\Property(property: 'quantity', type: 'integer'),
                    ],
                )),
                new OA\Property(property: 'customer_name', type: 'string'),
                new OA\Property(property: 'customer_phone', type: 'string'),
                new OA\Property(property: 'delivery_address', type: 'string'),
                new OA\Property(property: 'delivery_city', type: 'string', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
                new OA\Property(property: 'coupon_code', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Commande créée'),
            new OA\Response(response: 422, description: 'Erreur de validation ou panier invalide'),
        ],
    )]
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $items = array_map(
            fn (array $line) => new CheckoutItemInput((int) $line['offer_id'], (int) $line['quantity']),
            $request->array('items'),
        );

        $delivery = new DeliveryDetails(
            customerName: $request->string('customer_name')->toString(),
            customerPhone: $request->string('customer_phone')->toString(),
            address: $request->string('delivery_address')->toString(),
            city: $request->string('delivery_city')->toString() ?: null,
            notes: $request->string('notes')->toString() ?: null,
            customerEmail: $request->string('customer_email')->toString() ?: null,
        );

        try {
            // Optional: a logged-in customer's token attaches the order to
            // their account (so it shows up on /orders/mine) without making
            // auth required — guest checkout (cahier §5.1) still works.
            $order = $this->placeOrder->execute(
                $items,
                $delivery,
                $request->user('sanctum')?->id,
                $request->filled('coupon_code') ? $request->string('coupon_code')->toString() : null,
            );
        } catch (EmptyCartException|OfferUnavailableException|CouponRejectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new CustomerOrderResource($order))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/orders/{orderNumber}',
        summary: "Récupère une commande par son numéro (confirmation, suivi). Le numéro de commande seul n'est pas un secret : le téléphone du client sert de preuve de possession.",
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'orderNumber', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'phone', in: 'query', required: true, description: 'Téléphone fourni à la commande', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Commande trouvée'),
            new OA\Response(response: 404, description: "Commande introuvable ou téléphone ne correspondant pas (réponse identique pour ne pas révéler l'existence de la commande)"),
        ],
    )]
    public function show(string $orderNumber, ShowOrderRequest $request): CustomerOrderResource
    {
        $order = Order::query()->where('order_number', $orderNumber)->with('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents')->first();

        // Same 404 whether the order doesn't exist or the phone doesn't match —
        // the order number alone must never confirm an order's existence to a
        // third party (cahier §8 "lutte contre la fraude").
        if (! $order || ! hash_equals($order->customer_phone, $request->string('phone')->toString())) {
            throw new NotFoundHttpException;
        }

        return new CustomerOrderResource($order);
    }

    #[OA\Get(
        path: '/orders/mine',
        summary: 'Historique des commandes du client connecté (cahier des charges §5.1 "espace client")',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        responses: [new OA\Response(response: 200, description: 'Commandes du client')],
    )]
    public function mine(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents')
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        return CustomerOrderResource::collection($orders);
    }

    #[OA\Get(
        path: '/orders/mine/{orderNumber}',
        summary: "Suivi d'une commande du client connecté, avec ses colis et leur historique",
        security: [['sanctum' => []]],
        tags: ['Orders'],
        responses: [
            new OA\Response(response: 200, description: 'Commande du client'),
            new OA\Response(response: 404, description: 'Introuvable ou non rattachée au client'),
        ],
    )]
    public function mineShow(Request $request, string $orderNumber): CustomerOrderResource
    {
        $order = Order::query()
            ->where('customer_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with('items', 'subOrders.items', 'subOrders.statusEvents', 'statusEvents')
            ->first();

        // 404, not 403: another customer's order number must not be confirmable.
        if (! $order) {
            throw new NotFoundHttpException;
        }

        return new CustomerOrderResource($order);
    }
}
