<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Http\Resources\AnnouncementResource;
use App\Modules\Promotions\Infrastructure\Models\Announcement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class AnnouncementController extends Controller
{
    #[OA\Get(
        path: '/announcements',
        summary: 'Annonces flash à afficher maintenant sur la vitrine (la plus récente en premier)',
        tags: ['Promotions'],
        responses: [new OA\Response(response: 200, description: 'Annonces en cours')],
    )]
    public function index(): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(Announcement::query()->showing()->latest('id')->get());
    }
}
