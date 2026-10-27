<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Resources;

use App\Modules\Procurement\Infrastructure\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->resource;

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_name' => $supplier->contact_name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'payment_terms_days' => $supplier->payment_terms_days,
            'lead_time_days' => $supplier->lead_time_days,
            'notes' => $supplier->notes,
            'active' => $supplier->active,
        ];
    }
}
