<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $feature
 * @property string $subject_type role | user
 * @property string $subject
 * @property bool $enabled
 * @property int|null $updated_by
 */
class FeatureOverride extends Model
{
    public const ROLE = 'role';

    public const USER = 'user';

    protected $fillable = ['feature', 'subject_type', 'subject', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
