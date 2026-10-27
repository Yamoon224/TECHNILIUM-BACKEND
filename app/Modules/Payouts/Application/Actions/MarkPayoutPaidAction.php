<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Actions;

use App\Modules\Payouts\Domain\Exceptions\PayoutException;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use App\Modules\Shared\Events\PayoutPaid;
use Illuminate\Support\Facades\DB;

/** Records that the transfer was executed (bank / mobile money proof reference). */
final class MarkPayoutPaidAction
{
    public function execute(int $payoutId, string $reference): Payout
    {
        return DB::transaction(function () use ($payoutId, $reference) {
            // Lock so a double click or concurrent run cannot pay twice.
            $payout = Payout::query()->lockForUpdate()->findOrFail($payoutId);

            if (! $payout->status->canTransitionTo(PayoutStatus::Paid)) {
                throw PayoutException::alreadyPaid($payout->id);
            }

            $payout->status = PayoutStatus::Paid;
            $payout->payment_reference = $reference;
            $payout->paid_at = now();
            $payout->save();

            event(new PayoutPaid($payout->id, $payout->vendor_id, $payout->net_amount, $payout->currency));

            return $payout->load('items');
        });
    }
}
