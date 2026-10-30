<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Resources;

use App\Modules\Promotions\Infrastructure\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Announcement $announcement */
        $announcement = $this->resource;

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'image_url' => $announcement->url(),
            'link_url' => $announcement->link_url,
            'width' => $announcement->width,
            'height' => $announcement->height,
            'delay_seconds' => $announcement->delay_seconds,
            'max_displays' => $announcement->max_displays,
            'starts_at' => $announcement->starts_at?->toIso8601String(),
            'ends_at' => $announcement->ends_at?->toIso8601String(),
            'is_active' => $announcement->is_active,
            'archived_at' => $announcement->archived_at?->toIso8601String(),
            // Changes with every edit: the storefront shows an edited announcement again to visitors who closed the old one.
            'version' => $announcement->updated_at?->getTimestamp(),
        ];
    }
}
