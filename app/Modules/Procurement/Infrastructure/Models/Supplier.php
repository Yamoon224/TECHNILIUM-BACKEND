<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use App\Modules\Procurement\Infrastructure\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property int $payment_terms_days
 * @property int|null $lead_time_days
 * @property string|null $notes
 * @property bool $active
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address',
        'payment_terms_days', 'lead_time_days', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }
}
