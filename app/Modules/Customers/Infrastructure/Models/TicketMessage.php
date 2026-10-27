<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $author_type customer|staff
 * @property int $author_id
 * @property string $body
 * @property bool $internal
 * @property Carbon|null $created_at
 */
class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_type', 'author_id', 'body', 'internal'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }
}
