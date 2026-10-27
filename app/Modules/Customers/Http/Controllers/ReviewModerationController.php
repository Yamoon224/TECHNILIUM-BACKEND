<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Application\Actions\ModerateReviewAction;
use App\Modules\Customers\Domain\Exceptions\ReviewException;
use App\Modules\Customers\Http\Requests\RejectReviewRequest;
use App\Modules\Customers\Http\Resources\StaffReviewResource;
use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Manual moderation of reviews (cahier §5.9). Gated by can:manage-reviews —
 * support, marketing or administrateur.
 */
class ReviewModerationController extends Controller
{
    public function __construct(private readonly ModerateReviewAction $moderate) {}

    #[OA\Get(
        path: '/admin/reviews',
        summary: 'File de modération des avis, filtrable par statut ou produit (les avis signalés d\'abord)',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Avis')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Review::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        // Pending ones are the work to do: they come first.
        $query->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->orderByDesc('id');

        return StaffReviewResource::collection($query->paginate(max(1, min($request->integer('per_page', 30), 100))));
    }

    #[OA\Post(
        path: '/admin/reviews/{review}/publish',
        summary: 'Publie un avis (ou le rétablit après un rejet)',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Avis publié'), new OA\Response(response: 422, description: 'Déjà publié')],
    )]
    public function publish(Request $request, Review $review): StaffReviewResource|JsonResponse
    {
        return $this->run(fn () => $this->moderate->publish($review, $request->user()->id));
    }

    #[OA\Post(
        path: '/admin/reviews/{review}/reject',
        summary: 'Rejette un avis, ou le retire de la vitrine, avec un motif',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Avis rejeté'), new OA\Response(response: 422, description: 'Déjà rejeté')],
    )]
    public function reject(RejectReviewRequest $request, Review $review): StaffReviewResource|JsonResponse
    {
        return $this->run(fn () => $this->moderate->reject(
            $review,
            $request->user()->id,
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        ));
    }

    private function run(callable $decision): StaffReviewResource|JsonResponse
    {
        try {
            return new StaffReviewResource($decision());
        } catch (ReviewException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
