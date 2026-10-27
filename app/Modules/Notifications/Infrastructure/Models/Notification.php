<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Models;

use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One customer message and its delivery state. Stored in notifications_log
 * because the "notifications" table name belongs to Laravel's own system.
 *
 * @property int $id
 * @property string $dedupe_key
 * @property string $event
 * @property string $channel
 * @property string $recipient
 * @property string $body
 * @property string|null $reference
 * @property NotificationStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $table = 'notifications_log';

    protected $fillable = [
        'dedupe_key',
        'event',
        'channel',
        'recipient',
        'body',
        'reference',
        'status',
        'attempts',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }
}
