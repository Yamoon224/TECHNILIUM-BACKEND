<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Http\Requests\AnnouncementRequest;
use App\Modules\Promotions\Http\Resources\AnnouncementResource;
use App\Modules\Promotions\Infrastructure\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/** Flash announcements of the storefront. Gated by can:manage-promotions. */
class AnnouncementAdminController extends Controller
{
    #[OA\Get(
        path: '/admin/announcements',
        summary: 'Toutes les annonces flash (actives ou non, en cours ou expirées)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Annonces')],
    )]
    public function index(): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(Announcement::query()->latest('id')->get());
    }

    #[OA\Post(
        path: '/admin/announcements',
        summary: 'Crée une annonce flash (image JPG, PNG ou WebP, 4 Mo max)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [
            new OA\Response(response: 201, description: 'Annonce créée'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ],
    )]
    public function store(AnnouncementRequest $request): JsonResponse
    {
        // Server-chosen name: the client's file name is never used.
        $path = $request->file('image')->storePublicly('announcements', 'public');

        $announcement = Announcement::query()->create([...$this->fields($request), 'path' => $path]);

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/admin/announcements/{announcement}',
        summary: 'Modifie une annonce (multipart : une nouvelle image remplace l\'ancienne)',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Annonce modifiée')],
    )]
    public function update(AnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $data = $this->fields($request);

        if ($request->hasFile('image')) {
            $data['path'] = $request->file('image')->storePublicly('announcements', 'public');
            Storage::disk('public')->delete($announcement->path);
        }

        $announcement->update($data);

        return new AnnouncementResource($announcement->refresh());
    }

    #[OA\Post(
        path: '/admin/announcements/{announcement}/archive',
        summary: "Archive une annonce : elle n'est plus affichée mais reste dans l'historique",
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Annonce archivée')],
    )]
    public function archive(Announcement $announcement): AnnouncementResource
    {
        $announcement->update(['archived_at' => $announcement->archived_at ?? now()]);

        return new AnnouncementResource($announcement->refresh());
    }

    #[OA\Post(
        path: '/admin/announcements/{announcement}/restore',
        summary: "Sort une annonce de l'archive",
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Annonce restaurée')],
    )]
    public function restore(Announcement $announcement): AnnouncementResource
    {
        $announcement->update(['archived_at' => null]);

        return new AnnouncementResource($announcement->refresh());
    }

    #[OA\Delete(
        path: '/admin/announcements/{announcement}',
        summary: 'Supprime une annonce et son image',
        security: [['sanctum' => []]],
        tags: ['Promotions'],
        responses: [new OA\Response(response: 204, description: 'Annonce supprimée')],
    )]
    public function destroy(Announcement $announcement): JsonResponse
    {
        Storage::disk('public')->delete($announcement->path);
        $announcement->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(AnnouncementRequest $request): array
    {
        return [
            'title' => $request->string('title')->toString(),
            'link_url' => $request->string('link_url')->toString() ?: null,
            'width' => $request->integer('width'),
            'height' => $request->integer('height'),
            'delay_seconds' => $request->integer('delay_seconds'),
            'max_displays' => $request->filled('max_displays') ? $request->integer('max_displays') : null,
            'starts_at' => $request->input('starts_at') ?: null,
            'ends_at' => $request->input('ends_at') ?: null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
