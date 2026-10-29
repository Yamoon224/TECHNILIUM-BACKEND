<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One slide of a storefront banner. Slides of a placement scroll in position order.
 *
 * @property int $id
 * @property string $placement
 * @property string $path
 * @property string|null $title
 * @property string|null $link_url
 * @property int $position
 * @property bool $is_active
 */
class Banner extends Model
{
    public const PLACEMENTS = ['hero', 'footer'];

    public const MAX_PER_PLACEMENT = 10;

    protected $fillable = ['placement', 'path', 'title', 'link_url', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
