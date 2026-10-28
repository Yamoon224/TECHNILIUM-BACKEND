<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Http\Requests\Admin\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateProductRequest;
use App\Modules\Catalog\Http\Requests\Admin\UploadProductImageRequest;
use App\Modules\Catalog\Http\Resources\Admin\ProductResource;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Shared\ValueObjects\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Staff-facing catalog management (cahier §6.1 "PIM"). Gated by
 * can:manage-catalog — gestionnaire_catalogue or administrateur.
 *
 * Handles "simple" products: one variant, one Technilium (1P) offer per
 * product. Multi-vendor / multi-offer products belong to the Vendors module
 * once it exists.
 */
class ProductAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/products',
        summary: 'Liste paginée des produits pour le backoffice (tous statuts)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Produits')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $products = Product::query()
            ->with(['variants.offers'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    #[OA\Post(
        path: '/admin/products',
        summary: 'Crée un produit simple (une variante, une offre Technilium publiée)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 201, description: 'Produit créé'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::query()->create([
                'category_id' => $request->integer('category_id'),
                'brand' => $request->string('brand')->toString(),
                'name' => $request->string('name')->toString(),
                'slug' => $this->resolveSlug($request->string('slug')->toString(), $request->string('name')->toString()),
                'description' => $request->string('description')->toString() ?: null,
                'condition_state' => $request->string('condition_state')->toString() ?: 'neuf',
                'status' => 'published',
            ]);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $this->resolveSku($request->string('sku')->toString()),
            ]);

            Offer::query()->create([
                'product_variant_id' => $variant->id,
                'vendor_id' => null,
                'price_amount' => $request->integer('price_amount'),
                'currency' => $request->string('currency')->toString() ?: Currency::XOF->value,
                'warranty_months' => $request->integer('warranty_months', 0),
                'stock_quantity' => $request->integer('stock_quantity'),
                'status' => OfferStatus::Published->value,
                'published_at' => now(),
            ]);

            return $product;
        });

        $product->load(['variants.offers']);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/products/{product}/image',
        summary: 'Envoie la photo du produit (JPG, PNG ou WebP, 2 Mo max) et remplace l\'ancienne',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Photo enregistrée'),
            new OA\Response(response: 422, description: 'Fichier refusé'),
        ],
    )]
    public function uploadImage(UploadProductImageRequest $request, Product $product): ProductResource
    {
        // Server-chosen name: the client's file name is never used.
        $path = $request->file('image')->storePublicly('products', 'public');
        $previous = $product->image_path;

        $product->update(['image_path' => $path]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return new ProductResource($product->load('variants.offers'));
    }

    #[OA\Delete(
        path: '/admin/products/{product}/image',
        summary: 'Retire la photo du produit',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Photo retirée')],
    )]
    public function deleteImage(Product $product): ProductResource
    {
        if ($product->image_path !== null) {
            Storage::disk('public')->delete($product->image_path);
            $product->update(['image_path' => null]);
        }

        return new ProductResource($product->load('variants.offers'));
    }

    #[OA\Put(
        path: '/admin/products/{product}',
        summary: 'Met à jour un produit et son offre Technilium',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Produit mis à jour'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->fill($request->only(['category_id', 'brand', 'name', 'description', 'condition_state', 'status']));

        if ($request->filled('slug')) {
            $product->slug = $this->resolveSlug($request->string('slug')->toString(), $product->name);
        }

        $product->save();

        $offer = $product->variants->first()?->offers->first();

        if ($offer !== null) {
            $offer->fill($request->only(['price_amount', 'stock_quantity', 'warranty_months']));
            $offer->save();
        }

        $product->load(['variants.offers']);

        return new ProductResource($product);
    }

    #[OA\Delete(
        path: '/admin/products/{product}',
        summary: 'Archive un produit (retiré de la vitrine, conservé pour l\'historique des commandes)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Produit archivé')],
    )]
    public function destroy(Product $product): ProductResource
    {
        $product->update(['status' => 'archived']);

        foreach ($product->variants as $variant) {
            $variant->offers()->update(['status' => OfferStatus::Archived->value]);
        }

        $product->load(['variants.offers']);

        return new ProductResource($product);
    }

    private function resolveSlug(string $requested, string $name): string
    {
        return $requested !== '' ? $requested : Str::slug($name);
    }

    private function resolveSku(string $requested): string
    {
        return $requested !== '' ? $requested : 'TEC-'.Str::upper(Str::random(8));
    }
}
