<?php

declare(strict_types=1);

namespace App\Modules\Returns\Application\Actions;

use App\Modules\Returns\Domain\Exceptions\ReturnException;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shared\Events\ReturnRefunded;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every RMA status change. Refunding publishes
 * ReturnRefunded in the same transaction so the credit note is written
 * atomically with the decision.
 */
final class TransitionReturnAction
{
    public function execute(
        ReturnRequest $return,
        ReturnStatus $target,
        ?string $note = null,
        ?int $refundAmount = null,
    ): ReturnRequest {
        return DB::transaction(function () use ($return, $target, $note, $refundAmount) {
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

            $return->save();

            if ($target === ReturnStatus::Refunded) {
                event(new ReturnRefunded(
                    $return->id, $return->order_number, (int) $return->refunded_amount, $return->currency, $return->reason,
                ));
            }

            return $return;
        });
    }
}
