<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Resources;

use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Vendor $vendor */
        $vendor = $this->resource;

        return [
            'id' => $vendor->id,
            'company_name' => $vendor->company_name,
            'phone' => $vendor->phone,
            'address' => $vendor->address,
            'status' => $vendor->status->value,
            'created_at' => $vendor->created_at?->toIso8601String(),
        ];
    }
}
