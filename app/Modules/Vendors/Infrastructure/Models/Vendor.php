<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Infrastructure\Models;

use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $company_name
 * @property string $phone
 * @property string|null $address
 * @property VendorStatus $status
 * @property Carbon|null $created_at
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'phone',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
        ];
    }

    protected static function newFactory(): VendorFactory
    {
        return VendorFactory::new();
    }
}
