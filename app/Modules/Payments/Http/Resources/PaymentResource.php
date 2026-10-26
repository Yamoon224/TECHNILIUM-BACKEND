<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Infrastructure\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'order_number' => $payment->order_number,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'amount_amount' => $payment->amount_amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }
}
