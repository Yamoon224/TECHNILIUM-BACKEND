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
    /** Photos per product. */
    private const MAX_IMAGES = 8;

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
            ->with(['variants.offers', 'images'])
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
                'name_en' => $request->string('name_en')->toString() ?: null,
                'description_en' => $request->string('description_en')->toString() ?: null,
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

        $product->load(['variants.offers', 'images']);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/products/{product}/images',
        summary: 'Ajoute une photo à la galerie du produit (JPG, PNG ou WebP, 2 Mo max, 8 photos au plus)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Photo ajoutée'),
            new OA\Response(response: 422, description: 'Fichier refusé ou galerie pleine'),
        ],
    )]
    public function addImage(UploadProductImageRequest $request, Product $product): JsonResponse|ProductResource
    {
        if ($product->images()->count() >= self::MAX_IMAGES) {
            return response()->json([
                'message' => 'La galerie est pleine ('.self::MAX_IMAGES.' photos au plus).',
                'errors' => ['image' => ['La galerie est pleine.']],
            ], 422);
        }

        // Server-chosen name: the client's file name is never used.
        $path = $request->file('image')->storePublicly('products', 'public');

        // The first photo is the cover; the next ones go to the end.
        $next = $product->images()->exists() ? (int) $product->images()->max('position') + 1 : 0;
        $product->images()->create(['path' => $path, 'position' => $next]);

        return new ProductResource($product->load(['variants.offers', 'images']));
    }

    #[OA\Post(
        path: '/admin/products/{product}/images/{image}/cover',
        summary: 'Place une photo en première position (photo de couverture)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Photo de couverture changée')],
    )]
    public function setCover(Product $product, int $image): ProductResource
    {
        $cover = $product->images()->findOrFail($image);

        DB::transaction(function () use ($product, $cover) {
            // Cover first, the others keep their relative order after it.
            $ordered = $product->images()->where('id', '!=', $cover->id)->get()->prepend($cover);

            foreach ($ordered->values() as $position => $photo) {
                $photo->update(['position' => $position]);
            }
        });

        return new ProductResource($product->load(['variants.offers', 'images']));
    }

    #[OA\Delete(
        path: '/admin/products/{product}/images/{image}',
        summary: 'Retire une photo de la galerie',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Photo retirée')],
    )]
    public function deleteImage(Product $product, int $image): ProductResource
    {
        $photo = $product->images()->findOrFail($image);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return new ProductResource($product->load(['variants.offers', 'images']));
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
        $product->fill($request->only(['category_id', 'brand', 'name', 'name_en', 'description', 'description_en', 'condition_state', 'status']));

        if ($request->filled('slug')) {
            $product->slug = $this->resolveSlug($request->string('slug')->toString(), $product->name);
        }

        $product->save();

        $offer = $product->variants->first()?->offers->first();

        if ($offer !== null) {
            $offer->fill($request->only(['price_amount', 'stock_quantity', 'warranty_months']));
            $offer->save();
        }

        $product->load(['variants.offers', 'images']);

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

        $product->load(['variants.offers', 'images']);

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
