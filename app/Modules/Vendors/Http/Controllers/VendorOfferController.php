<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Controllers;

use App\Modules\Catalog\Application\Contracts\VendorOfferGatewayInterface;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Http\Requests\StoreVendorOfferRequest;
use App\Modules\Vendors\Http\Requests\UpdateVendorOfferRequest;
use App\Modules\Vendors\Http\Resources\VendorOfferResource;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * A vendor's self-service listing management. Gated by can:be-vendor (role
 * only); an Active Vendor profile is also required, checked here since
 * Vendors owns that data.
 */
class VendorOfferController extends Controller
{
    public function __construct(
        private readonly VendorOfferGatewayInterface $offers,
    ) {}

    #[OA\Get(
        path: '/vendors/me/offers',
        summary: 'Liste les offres du vendeur connecté',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [new OA\Response(response: 200, description: 'Offres du vendeur')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $vendor = $this->resolveActiveVendor($request);

        return VendorOfferResource::collection($this->offers->listOffersForVendor($vendor->id));
    }

    #[OA\Post(
        path: '/vendors/me/offers',
        summary: 'Soumet une nouvelle offre (envoyée en modération)',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [
            new OA\Response(response: 201, description: 'Offre soumise'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StoreVendorOfferRequest $request): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        $offer = $this->offers->createOffer($request->integer('variant_id'), $vendor->id, [
            'price_amount' => $request->integer('price_amount'),
            'stock_quantity' => $request->integer('stock_quantity'),
            'warranty_months' => $request->integer('warranty_months', 0),
        ]);

        return (new VendorOfferResource($offer))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/vendors/me/offers/{offer}',
        summary: 'Met à jour le prix/stock d\'une offre du vendeur',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [
            new OA\Response(response: 200, description: 'Offre mise à jour'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
        ],
    )]
    public function update(UpdateVendorOfferRequest $request, int $offer): VendorOfferResource
    {
        $vendor = $this->resolveActiveVendor($request);

        $updated = $this->offers->updateOffer($offer, $vendor->id, $request->only([
            'price_amount', 'stock_quantity', 'warranty_months',
        ]));

        if ($updated === null) {
            abort(404, 'Offre introuvable.');
        }

        return new VendorOfferResource($updated);
    }

    #[OA\Delete(
        path: '/vendors/me/offers/{offer}',
        summary: 'Retire une offre du vendeur (archivage)',
        security: [['sanctum' => []]],
        tags: ['Vendors'],
        responses: [
            new OA\Response(response: 204, description: 'Offre archivée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
        ],
    )]
    public function destroy(Request $request, int $offer): JsonResponse
    {
        $vendor = $this->resolveActiveVendor($request);

        if (! $this->offers->archiveOffer($offer, $vendor->id)) {
            abort(404, 'Offre introuvable.');
        }

        return response()->json(null, 204);
    }

    private function resolveActiveVendor(Request $request): Vendor
    {
        /** @var Vendor|null $vendor */
        $vendor = Vendor::query()->where('user_id', $request->user()->id)->first();

        if (! $vendor || $vendor->status !== VendorStatus::Active) {
            abort(403, "Ce compte n'a pas de profil vendeur actif.");
        }

        return $vendor;
    }
}
