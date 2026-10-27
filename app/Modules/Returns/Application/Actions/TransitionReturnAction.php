<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Actions;

use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shared\Events\ReturnReceived;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every RMA status change. Refunding publishes
 * ReturnRefunded in the same transaction so the credit note and the payment
 * refund are written atomically with the decision; receiving publishes
 * ReturnReceived only when the inspector judged the articles resellable.
 */
final class TransitionReturnAction
{
    public function execute(
        ReturnRequest $return,
        ReturnStatus $target,
        ?string $note = null,
        ?int $refundAmount = null,
        bool $restock = false,
    ): ReturnRequest {
        return DB::transaction(function () use ($return, $target, $note, $refundAmount, $restock) {
            $return = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if (! $return->status->canTransitionTo($target)) {
                throw ReturnException::invalidTransition($return->status, $target);
            }

            $return->status = $target;
            $return->decision_note = $note ?? $return->decision_note;

            if ($target === ReturnStatus::Refunded) {
                $amount = $refundAmount ?? $return->requested_amount;

                // The refund can be lower than requested (partial acceptance), never higher.
                if ($amount < 1 || $amount > $return->requested_amount) {
                    throw ReturnException::amountExceedsRefundable($return->requested_amount);
                }

                $return->refunded_amount = $amount;
                $return->refunded_at = now();
            }

            $shouldRestock = $target === ReturnStatus::Received && $restock && ! empty($return->items);
            $return->restocked = $shouldRestock;

            $return->save();

            if ($shouldRestock) {
                event(new ReturnReceived($return->id, $return->order_number, $return->items));
            }

            if ($target === ReturnStatus::Refunded) {
                event(new ReturnRefunded(
                    $return->id, $return->order_number, (int) $return->refunded_amount, $return->currency, $return->reason,
                ));
            }

            return $return;
        });
    }
}
