<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\TransitionOfferStatusAction;
use App\Modules\Catalog\Domain\Exceptions\InvalidOfferTransitionException;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Http\Resources\Admin\OfferResource;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Vendor offer moderation ("file de validation", cahier §6.1). Gated by
 * can:manage-catalog — gestionnaire_catalogue or administrateur.
 */
class OfferModerationController extends Controller
{
    #[OA\Get(
        path: '/admin/offers',
        summary: 'Liste les offres (par défaut celles en attente de modération)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Offres')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString() ?: OfferStatus::PendingReview->value;

        $offers = Offer::query()
            ->where('status', $status)
            ->with('variant.product')
            ->orderBy('created_at')
            ->get();

        return OfferResource::collection($offers);
    }

    #[OA\Post(
        path: '/admin/offers/{offer}/approve',
        summary: 'Publie une offre en attente de modération',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Offre publiée'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function approve(Offer $offer): OfferResource|JsonResponse
    {
        return $this->transition($offer, OfferStatus::Published);
    }

    #[OA\Post(
        path: '/admin/offers/{offer}/reject',
        summary: 'Rejette une offre en attente de modération',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Offre rejetée'),
            new OA\Response(response: 422, description: 'Transition impossible'),
        ],
    )]
    public function reject(Offer $offer): OfferResource|JsonResponse
    {
        return $this->transition($offer, OfferStatus::Rejected);
    }

    private function transition(Offer $offer, OfferStatus $target): OfferResource|JsonResponse
    {
        try {
            $offer = (new TransitionOfferStatusAction)->execute($offer, $target);
        } catch (InvalidOfferTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new OfferResource($offer->loadMissing('variant.product'));
    }
}
