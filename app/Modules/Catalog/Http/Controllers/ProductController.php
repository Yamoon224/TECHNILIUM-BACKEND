<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Infrastructure\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/catalog/products',
        summary: 'Liste paginée des produits publiés, filtrable par catégorie (cahier des charges §5.2)',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Produits publiés')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('status', ProductStatus::Published)
            ->with([
                'category',
                'variants.offers' => fn ($query) => $query->where('status', OfferStatus::Published),
            ])
            ->when(
                $request->string('category')->isNotEmpty(),
                fn ($query) => $query->whereHas(
                    'category',
                    fn ($category) => $category->where('slug', $request->string('category')->toString()),
                ),
            )
            ->when(
                $request->string('search')->isNotEmpty(),
                fn ($query) => $query->where(function ($query) use ($request) {
                    $term = '%'.$request->string('search')->toString().'%';
                    $query->where('name', 'like', $term)->orWhere('brand', 'like', $term);
                }),
            )
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        return ProductResource::collection($products);
    }

    #[OA\Get(
        path: '/catalog/products/{slug}',
        summary: 'Fiche produit détaillée avec variantes et offres (cahier des charges §5.3)',
        tags: ['Catalog'],
        parameters: [new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Fiche produit'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ],
    )]
    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', ProductStatus::Published)
            ->with([
                'category',
                'variants.offers' => fn ($query) => $query->where('status', OfferStatus::Published),
            ])
            ->firstOrFail();

        return new ProductResource($product);
    }
}
