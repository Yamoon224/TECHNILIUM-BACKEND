<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Application\Actions\SubmitReviewAction;
use App\Modules\Customers\Application\ReviewEligibility;
use App\Modules\Customers\Domain\Exceptions\ReviewException;
use App\Modules\Customers\Domain\RatingSummary;
use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Http\Requests\SubmitReviewRequest;
use App\Modules\Customers\Http\Resources\MyReviewResource;
use App\Modules\Customers\Http\Resources\ReviewResource;
use App\Modules\Customers\Infrastructure\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

/**
 * Product reviews, storefront side (cahier §5.9): anyone reads the published
 * ones; only a verified buyer writes.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly SubmitReviewAction $submitReview,
        private readonly ReviewEligibility $eligibility,
    ) {}

    #[OA\Get(
        path: '/reviews/products/{productId}',
        summary: 'Avis publiés d\'un produit, avec la note moyenne et la répartition',
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Avis et synthèse')],
    )]
    public function forProduct(Request $request, int $productId): JsonResponse
    {
        $published = Review::query()->where('product_id', $productId)->where('status', ReviewStatus::Published);

        $counts = (clone $published)->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating')->all();

        $reviews = $published->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 10), 50)));

        return response()->json([
            'summary' => RatingSummary::fromCounts(array_map('intval', $counts)),
            'reviews' => ReviewResource::collection($reviews)->response()->getData(true),
        ]);
    }

    #[OA\Get(
        path: '/reviews/products/{productId}/eligibility',
        summary: 'Le client connecté peut-il donner un avis sur ce produit ?',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Éligibilité et raison du refus')],
    )]
    public function eligibility(Request $request, int $productId): JsonResponse
    {
        $check = $this->eligibility->check($request->user()->id, $productId);

        // The order number proves the purchase to the server; the client only needs the verdict.
        return response()->json(['can_review' => $check['can_review'], 'reason' => $check['reason']]);
    }

    #[OA\Post(
        path: '/reviews',
        summary: 'Donne un avis sur un produit reçu (publié tout de suite, ou en modération si signalé)',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [
            new OA\Response(response: 201, description: 'Avis enregistré'),
            new OA\Response(response: 422, description: 'Achat non vérifié, avis déjà donné ou données invalides'),
        ],
    )]
    public function store(SubmitReviewRequest $request): JsonResponse
    {
        try {
            $review = $this->submitReview->execute(
                $request->user()->id,
                (string) $request->user()->name,
                $request->integer('product_id'),
                $request->integer('rating'),
                $request->filled('title') ? $request->string('title')->toString() : null,
                $request->filled('body') ? $request->string('body')->toString() : null,
            );
        } catch (ReviewException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new MyReviewResource($review))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/reviews/mine',
        summary: 'Mes avis, avec leur état de modération',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        responses: [new OA\Response(response: 200, description: 'Avis du client connecté')],
    )]
    public function mine(Request $request): AnonymousResourceCollection
    {
        return MyReviewResource::collection(
            Review::query()->where('customer_id', $request->user()->id)->orderByDesc('id')->paginate(50),
        );
    }
}
