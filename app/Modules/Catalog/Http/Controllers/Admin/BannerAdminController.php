<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\Admin\StoreBannerRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateBannerRequest;
use App\Modules\Catalog\Http\Resources\BannerResource;
use App\Modules\Catalog\Infrastructure\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Slides of the storefront banners (hero on top of the home page, footer band
 * above the page footer). Gated by can:manage-catalog.
 */
class BannerAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/banners',
        summary: 'Tous les slides des bannières (actifs ou non)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Slides')],
    )]
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(Banner::query()->orderBy('placement')->orderBy('position')->orderBy('id')->get());
    }

    #[OA\Post(
        path: '/admin/banners',
        summary: 'Ajoute un slide à une bannière (JPG, PNG ou WebP, 4 Mo max, 10 slides au plus par bannière)',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [
            new OA\Response(response: 201, description: 'Slide ajouté'),
            new OA\Response(response: 422, description: 'Fichier refusé ou bannière pleine'),
        ],
    )]
    public function store(StoreBannerRequest $request): JsonResponse
    {
        $placement = $request->string('placement')->toString();

        if (Banner::query()->where('placement', $placement)->count() >= Banner::MAX_PER_PLACEMENT) {
            return response()->json([
                'message' => 'La bannière est pleine ('.Banner::MAX_PER_PLACEMENT.' images au plus).',
                'errors' => ['image' => ['La bannière est pleine.']],
            ], 422);
        }

        // Server-chosen name: the client's file name is never used.
        $path = $request->file('image')->storePublicly('banners', 'public');
        $next = Banner::query()->where('placement', $placement)->exists()
            ? (int) Banner::query()->where('placement', $placement)->max('position') + 1
            : 0;

        $banner = Banner::query()->create([
            'placement' => $placement,
            'path' => $path,
            'title' => $request->string('title')->toString() ?: null,
            'link_url' => $request->string('link_url')->toString() ?: null,
            'position' => $next,
        ]);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/admin/banners/{banner}',
        summary: 'Modifie le texte, le lien, l\'ordre ou la visibilité d\'un slide',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 200, description: 'Slide modifié')],
    )]
    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        $banner->update($request->validated());

        return new BannerResource($banner->refresh());
    }

    #[OA\Delete(
        path: '/admin/banners/{banner}',
        summary: 'Supprime un slide et son fichier',
        security: [['sanctum' => []]],
        tags: ['Catalog Admin'],
        responses: [new OA\Response(response: 204, description: 'Slide supprimé')],
    )]
    public function destroy(Banner $banner): JsonResponse
    {
        Storage::disk('public')->delete($banner->path);
        $banner->delete();

        return response()->json(null, 204);
    }
}
