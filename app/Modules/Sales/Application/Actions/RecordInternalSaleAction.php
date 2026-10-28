<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Actions;

use App\Modules\Catalog\Application\Contracts\CheckoutGatewayInterface;
use App\Modules\Orders\Application\Actions\PlaceOrderAction;
use App\Modules\Orders\Application\Actions\TransitionOrderStatusAction;
use App\Modules\Orders\Application\Data\CheckoutItemInput;
use App\Modules\Orders\Application\Data\DeliveryDetails;
use App\Modules\Orders\Domain\Exceptions\OfferUnavailableException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\Actions\CollectPaymentAction;
use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Sales\Application\InternalSaleException;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use Illuminate\Support\Facades\DB;

/**
 * Rings up a sale at the counter as one atomic business operation: the order is placed (stock
 * out, coupon redeemed), moved to delivered (invoice, cost of sales), and its payment collected
 * (ledger entry) — so the receipt exists the moment the sale does. Any failure rolls it all back.
 */
final class RecordInternalSaleAction
{
    public function __construct(
        private readonly CheckoutGatewayInterface $catalog,
        private readonly PlaceOrderAction $placeOrder,
        private readonly TransitionOrderStatusAction $transition,
        private readonly CollectPaymentAction $collectPayment,
    ) {}

    /**
     * @param  list<CheckoutItemInput>  $items
     * @return array{order: Order, payment: Payment}
     */
    public function execute(
        array $items,
        DeliveryDetails $customer,
        PaymentMethod $method,
        int $sellerId,
        ?string $provider = null,
        ?string $providerReference = null,
        ?int $tenderedAmount = null,
        ?string $couponCode = null,
    ): array {
        foreach ($items as $item) {
            $offer = $this->catalog->find($item->offerId);

            if ($offer === null) {
                throw OfferUnavailableException::notFound($item->offerId);
            }

            // Third-party stock is shipped and paid out by its vendor: not ours to sell over the counter.
            if ($offer->vendorId !== null) {
                throw InternalSaleException::vendorOffer($item->offerId);
            }
        }

        return DB::transaction(function () use ($items, $customer, $method, $sellerId, $provider, $providerReference, $tenderedAmount, $couponCode) {
            $order = $this->placeOrder->execute($items, $customer, null, $couponCode, 'internal', $sellerId, $method->value);

            if ($method === PaymentMethod::Cash && $tenderedAmount !== null && $tenderedAmount < $order->subtotal_amount) {
                throw InternalSaleException::insufficientCash($order->subtotal_amount, $tenderedAmount);
            }

            // The goods leave with the customer: confirmed (invoice), shipped, delivered (cost of sales).
            foreach ([OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered] as $step) {
                $order = $this->transition->execute($order, $step);
            }

            $payment = $this->collectPayment->execute(
                $order->order_number,
                $method,
                $order->subtotal_amount,
                $order->currency,
                $provider,
                $providerReference,
                $method === PaymentMethod::Cash ? $tenderedAmount : null,
                $sellerId,
            );

            InternalSale::query()->create([
                'order_number' => $order->order_number,
                'seller_id' => $sellerId,
                'payment_id' => $payment->id,
                'method' => $method->value,
                'amount' => $order->subtotal_amount,
                'currency' => $order->currency,
            ]);

            return ['order' => $order->load('items'), 'payment' => $payment];
        });
    }
}
