<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Infrastructure\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/catalog/categories',
        summary: 'Arborescence des catégories actives (cahier des charges §5.2)',
        tags: ['Catalog'],
        responses: [new OA\Response(response: 200, description: 'Arbre des catégories')],
    )]
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('position')
            ->get();

        return CategoryResource::collection($categories);
    }
}
