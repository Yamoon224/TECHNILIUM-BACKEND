<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Records money that is already in hand (a counter sale): the payment is born
 * completed, through the same transition as any other so the ledger entry is posted.
 */
final class CollectPaymentAction
{
    public function __construct(private readonly TransitionPaymentStatusAction $transition) {}

    public function execute(
        string $orderNumber,
        PaymentMethod $method,
        int $amount,
        string $currency,
        ?string $provider = null,
        ?string $providerReference = null,
        ?int $tenderedAmount = null,
        ?int $createdBy = null,
    ): Payment {
        return DB::transaction(function () use ($orderNumber, $method, $amount, $currency, $provider, $providerReference, $tenderedAmount, $createdBy) {
            $payment = Payment::query()->create([
                'order_number' => $orderNumber,
                'method' => $method->value,
                'status' => PaymentStatus::Pending->value,
                'amount_amount' => $amount,
                'currency' => $currency,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'tendered_amount' => $tenderedAmount,
                'created_by' => $createdBy,
            ]);

            return $this->transition->execute($payment, PaymentStatus::Completed);
        });
    }
}
