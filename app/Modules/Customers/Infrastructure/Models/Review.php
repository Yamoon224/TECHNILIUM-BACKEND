<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $customer_id
 * @property string $order_number
 * @property int $rating
 * @property string|null $title
 * @property string|null $body
 * @property string $author_name
 * @property ReviewStatus $status
 * @property string|null $rejection_reason
 * @property int|null $moderated_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customer_id',
        'order_number',
        'rating',
        'title',
        'body',
        'author_name',
        'status',
        'rejection_reason',
        'moderated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
}
