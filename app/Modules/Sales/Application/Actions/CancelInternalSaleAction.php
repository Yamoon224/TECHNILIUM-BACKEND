<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Returns\Application\Actions\OpenReturnAction;
use App\Modules\Returns\Application\Actions\TransitionReturnAction;
use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Sales\Application\InternalSaleException;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a counter sale: the whole sale goes back through the returns circuit in one go - the
 * articles return to stock and their cost of sales is reversed, the invoice is cancelled by a
 * credit note, the customer is refunded in full and the ledger reversed. Cancelling is a
 * refund, not an erasure: the order, invoice and receipt all stay on record.
 */
final class CancelInternalSaleAction
{
    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly OpenReturnAction $openReturn,
        private readonly TransitionReturnAction $transition,
    ) {}

    public function execute(string $orderNumber, string $reason, int $userId, bool $byManagement = false): InternalSale
    {
        return DB::transaction(function () use ($orderNumber, $reason, $userId, $byManagement) {
            $sale = InternalSale::query()->where('order_number', $orderNumber)->lockForUpdate()->first()
                ?? throw InternalSaleException::notFound($orderNumber);

            if ($sale->cancelled_at !== null) {
                throw InternalSaleException::alreadyCancelled($orderNumber);
            }

            $order = $this->orders->find($orderNumber) ?? throw InternalSaleException::notFound($orderNumber);

            $items = [];
            foreach ($order->lines as $line) {
                if ($line->offerId !== null) {
                    $items[] = ['offer_id' => $line->offerId, 'quantity' => $line->quantity];
                }
            }

            // The full paid amount comes back, whatever coupon was used.
            $return = $this->openReturn->execute($orderNumber, "Annulation vente interne : {$reason}", $order->subtotalAmount, $userId, $items);
            $return = $this->transition->execute($return, ReturnStatus::Accepted);
            $return = $this->transition->execute($return, ReturnStatus::Received, restock: true);
            $this->transition->execute($return, ReturnStatus::Refunded, refundAmount: $order->subtotalAmount);

            // Management cancelling remotely does not hand cash over: the refund falls on the seller's
            // drawer. A cashier who cancels pays it out of their own.
            $sale->update([
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'refund_till_user_id' => $byManagement ? $sale->seller_id : $userId,
                'cancel_reason' => $reason,
            ]);

            return $sale;
        });
    }
}
