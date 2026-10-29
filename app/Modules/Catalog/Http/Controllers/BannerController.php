<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\BannerResource;
use App\Modules\Catalog\Infrastructure\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

class BannerController extends Controller
{
    #[OA\Get(
        path: '/catalog/banners',
        summary: 'Slides actifs d\'une bannière de la vitrine (hero ou footer), dans l\'ordre de défilement',
        tags: ['Catalog'],
        responses: [new OA\Response(response: 200, description: 'Slides')],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $placement = $request->query('placement');

        $banners = Banner::query()
            ->where('is_active', true)
            ->when(is_string($placement) && in_array($placement, Banner::PLACEMENTS, true), fn ($query) => $query->where('placement', $placement))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return BannerResource::collection($banners);
    }
}
