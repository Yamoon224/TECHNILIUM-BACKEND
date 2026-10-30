<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Infrastructure\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A flash announcement: an image shown in a pop-up on the storefront's home page.
 *
 * @property int $id
 * @property string $title
 * @property string $path
 * @property string|null $link_url
 * @property int $width
 * @property int $height
 * @property int $delay_seconds
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property bool $is_active
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'path', 'link_url', 'width', 'height', 'delay_seconds', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'delay_seconds' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Active announcements whose period includes now (an open end means no limit).
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeShowing(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
