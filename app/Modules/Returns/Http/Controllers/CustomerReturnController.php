<?php

declare(strict_types=1);

namespace App\Modules\Returns\Http\Controllers;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Returns\Application\Actions\OpenReturnAction;
use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Returns\Http\Requests\CustomerReturnRequest;
use App\Modules\Returns\Http\Resources\ReturnResource;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * A customer's own returns. Ownership is the order's customer; anything else
 * - someone else's order, a guest order, an unknown number - answers 404,
 * never 403, so order numbers cannot be probed. The amount is never taken
 * from the customer: it follows from the articles they name.
 */
class CustomerReturnController extends Controller
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly OpenReturnAction $openReturn,
    ) {}

    #[OA\Get(
        path: '/customer/orders/{orderNumber}/returns',
        summary: "Retours demandés sur une commande du client connecté, avec leur avancement",
        security: [['sanctum' => []]],
        tags: ['Returns'],
        responses: [
            new OA\Response(response: 200, description: 'Retours'),
            new OA\Response(response: 404, description: 'Commande introuvable ou non rattachée au client'),
        ],
    )]
    public function forOrder(Request $request, string $orderNumber): JsonResponse
    {
        abort_unless($this->owns($request, $orderNumber), 404);

        $returns = ReturnRequest::query()->where('order_number', $orderNumber)->orderBy('id')->get();

        return response()->json(['data' => ReturnResource::collection($returns)->resolve($request)]);
    }

    #[OA\Post(
        path: '/customer/returns',
        summary: "Demande le retour d'articles d'une commande livrée du client connecté",
        security: [['sanctum' => []]],
        tags: ['Returns'],
        responses: [
            new OA\Response(response: 201, description: 'Retour demandé'),
            new OA\Response(response: 404, description: 'Commande introuvable ou non rattachée au client'),
            new OA\Response(response: 422, description: 'Commande non livrée, délai dépassé ou articles non retournables'),
        ],
    )]
    public function store(CustomerReturnRequest $request): ReturnResource|JsonResponse
    {
        $orderNumber = $request->string('order_number')->toString();

        abort_unless($this->owns($request, $orderNumber), 404);

        try {
            $return = $this->openReturn->execute(
                $orderNumber,
                $request->string('reason')->toString(),
                null,
                $request->user()->id,
                $request->input('items'),
            );
        } catch (ReturnException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new ReturnResource($return))->response()->setStatusCode(201);
    }

    private function owns(Request $request, string $orderNumber): bool
    {
        $order = $this->orders->find($orderNumber);

        return $order !== null
            && $order->customerId !== null
            && $order->customerId === $request->user()->id;
    }
}
