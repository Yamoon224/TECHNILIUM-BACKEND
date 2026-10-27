<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Infrastructure\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'user_name' => $entry->user_name,
            'roles' => $entry->roles,
            'method' => $entry->method,
            'path' => $entry->path,
            'route' => $entry->route,
            'status' => $entry->status,
            'payload' => $entry->payload,
            'ip' => $entry->ip,
            'occurred_at' => $entry->occurred_at->toIso8601String(),
        ];
    }
}
