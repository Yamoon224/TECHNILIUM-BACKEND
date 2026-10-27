<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use App\Modules\Customers\Domain\TicketCategory;
use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketSla;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $subject
 * @property TicketCategory $category
 * @property string|null $order_number
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assigned_to
 * @property Carbon|null $first_responded_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TicketMessage> $messages
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'subject',
        'category',
        'order_number',
        'priority',
        'status',
        'assigned_to',
        'first_responded_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function number(): string
    {
        return 'SAV-'.sprintf('%06d', $this->id);
    }

    public function isOverdue(): bool
    {
        return app(TicketSla::class)->isOverdue(
            $this->status, $this->priority, $this->created_at ?? now(), $this->first_responded_at, now(),
        );
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
