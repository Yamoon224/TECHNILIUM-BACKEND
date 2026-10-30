<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Models;

use App\Modules\Audit\Domain\Exceptions\AuditException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only: an entry is never updated or deleted through the application.
 * (Someone editing the database directly is caught by the signature chain.)
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $roles
 * @property string $method
 * @property string $path
 * @property string|null $route
 * @property int $status
 * @property array<array-key, mixed>|null $payload
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 * @property string $previous_hash
 * @property string $hash
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'roles',
        'method',
        'path',
        'route',
        'status',
        'payload',
        'ip',
        'user_agent',
        'occurred_at',
        'previous_hash',
        'hash',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw AuditException::immutable());
        static::deleting(fn () => throw AuditException::immutable());
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The fields the signature covers - everything except the hashes themselves.
     *
     * @return array<string, mixed>
     */
    public function signedContent(): array
    {
        return [
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'roles' => $this->roles,
            'method' => $this->method,
            'path' => $this->path,
            'route' => $this->route,
            'status' => $this->status,
            'payload' => $this->payload,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            // Wall-clock string exactly as stored: converting time zones here
            // would make a valid entry fail verification when the app zone is not UTC.
            'occurred_at' => $this->occurred_at->format('Y-m-d H:i:s'),
        ];
    }
}
