<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Http\Request;

/**
 * The order as its customer sees it: the same payload plus the delivery
 * confirmation code. Staff and courier endpoints use OrderResource, which
 * never carries the code — the courier must have to ask the customer for it.
 */
class CustomerOrderResource extends OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return parent::toArray($request) + ['delivery_code' => $order->delivery_code];
    }
}
