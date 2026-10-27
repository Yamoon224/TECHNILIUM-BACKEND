<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Controllers;

use App\Modules\Shipping\Application\Actions\CreateShipmentAction;
use App\Modules\Shipping\Application\Actions\TransitionShipmentStatusAction;
use App\Modules\Shipping\Application\Actions\ConfirmDeliveryAction;
use App\Modules\Shipping\Domain\Exceptions\DeliveryCodeException;
use App\Modules\Shipping\Domain\Exceptions\InvalidShipmentTransitionException;
use App\Modules\Shipping\Http\Requests\DeliverShipmentRequest;
use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Http\Requests\AssignShipmentRequest;
use App\Modules\Shipping\Http\Requests\FailShipmentRequest;
use App\Modules\Shipping\Http\Requests\StoreShipmentRequest;
use App\Modules\Shipping\Http\Resources\ShipmentResource;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Delivery tracking (cahier §8). Gated by can:manage-shipments —
 * logistique or administrateur.
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly CreateShipmentAction $createShipment,
        private readonly TransitionShipmentStatusAction $transitionShipmentStatus,
        private readonly ConfirmDeliveryAction $confirmDelivery,
    ) {}

    #[OA\Get(
        path: '/admin/shipments',
        summary: 'Liste les livraisons, filtrables par statut ou livreur assigné',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [new OA\Response(response: 200, description: 'Livraisons')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $query = Shipment::query()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        return ShipmentResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/admin/shipments',
        summary: 'Crée une livraison pour une commande',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 201, description: 'Livraison créée'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->createShipment->execute(
            $request->string('order_number')->toString(),
            $request->filled('assigned_to') ? $request->integer('assigned_to') : null,
        );

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/assign',
        summary: 'Assigne ou réassigne un livreur',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Livreur assigné'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function assign(AssignShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment->update(['assigned_to' => $request->integer('assigned_to')]);

        return new ShipmentResource($shipment);
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/start',
        summary: 'Marque la livraison comme en cours (sortie du livreur)',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Livraison en cours'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function start(Shipment $shipment): ShipmentResource|JsonResponse
    {
        return $this->transition($shipment, ShipmentStatus::InTransit);
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/deliver',
        summary: 'Confirme la livraison avec le code remis au client (preuve de remise)',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Livraison confirmée'),
            new OA\Response(response: 422, description: 'Code incorrect, livraison verrouillée ou transition impossible'),
        ],
    )]
    public function deliver(DeliverShipmentRequest $request, Shipment $shipment): ShipmentResource|JsonResponse
    {
        try {
            $shipment = $this->confirmDelivery->execute($shipment, $request->string('confirmation_code')->toString());
        } catch (InvalidShipmentTransitionException|DeliveryCodeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new ShipmentResource($shipment);
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/fail',
        summary: 'Enregistre une tentative de livraison échouée',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Tentative échouée enregistrée'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function fail(FailShipmentRequest $request, Shipment $shipment): ShipmentResource|JsonResponse
    {
        return $this->transition(
            $shipment,
            ShipmentStatus::FailedAttempt,
            $request->string('notes')->toString() ?: null,
        );
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/retry',
        summary: 'Relance une nouvelle tentative après un échec',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Nouvelle tentative en cours'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function retry(Shipment $shipment): ShipmentResource|JsonResponse
    {
        return $this->transition($shipment, ShipmentStatus::InTransit);
    }

    #[OA\Post(
        path: '/admin/shipments/{shipment}/return',
        summary: 'Retourne le colis (livraison abandonnée)',
        security: [['sanctum' => []]],
        tags: ['Shipping'],
        responses: [
            new OA\Response(response: 200, description: 'Colis retourné'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function returnToSender(Shipment $shipment): ShipmentResource|JsonResponse
    {
        return $this->transition($shipment, ShipmentStatus::Returned);
    }

    private function transition(Shipment $shipment, ShipmentStatus $target, ?string $notes = null): ShipmentResource|JsonResponse
    {
        try {
            $shipment = $this->transitionShipmentStatus->execute($shipment, $target, $notes);
        } catch (InvalidShipmentTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new ShipmentResource($shipment);
    }
}
