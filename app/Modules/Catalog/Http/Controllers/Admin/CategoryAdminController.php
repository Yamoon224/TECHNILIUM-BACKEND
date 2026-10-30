<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateCategoryRequest;
use App\Modules\Catalog\Http\Resources\Admin\CategoryResource;
use App\Modules\Catalog\Infrastructure\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Staff-facing catalog management (cahier §6.1 "PIM"). Gated by
 * can:manage-catalog - gestionnaire_catalogue or administrateur.
 */
class CategoryAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/categories',
        summary: 'Liste complète des catégories pour le backoffice (actives et inactives)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Catégories')],
    )]
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position')
            ->get();

        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/admin/categories',
        summary: 'Crée une catégorie',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 201, description: 'Catégorie créée'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create([
            'parent_id' => $request->integer('parent_id') ?: null,
            'name' => $request->string('name')->toString(),
            'slug' => $this->resolveSlug($request->string('slug')->toString(), $request->string('name')->toString()),
            'name_en' => $request->string('name_en')->toString() ?: null,
            'description' => $request->string('description')->toString() ?: null,
            'position' => $request->integer('position', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/admin/categories/{category}',
        summary: 'Met à jour une catégorie',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Catégorie mise à jour'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->fill($request->only(['parent_id', 'name', 'name_en', 'description', 'position', 'is_active']));

        if ($request->filled('slug')) {
            $category->slug = $this->resolveSlug($request->string('slug')->toString(), $category->name);
        }

        $category->save();

        return new CategoryResource($category);
    }

    #[OA\Delete(
        path: '/admin/categories/{category}',
        summary: 'Supprime une catégorie (refusé si des produits y sont encore rattachés)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 204, description: 'Catégorie supprimée'),
            new OA\Response(response: 409, description: 'Des produits sont encore rattachés à cette catégorie'),
        ],
    )]
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : des produits ou sous-catégories sont encore rattachés à cette catégorie.',
            ], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    private function resolveSlug(string $requested, string $name): string
    {
        return $requested !== '' ? $requested : Str::slug($name);
    }
}
