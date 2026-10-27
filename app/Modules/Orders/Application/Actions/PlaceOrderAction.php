<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Modules\Catalog\Application\Contracts\CheckoutGatewayInterface;
use App\Modules\Catalog\Domain\Exceptions\InsufficientStockException;
use App\Modules\Orders\Application\Data\CheckoutItemInput;
use App\Modules\Orders\Application\Data\DeliveryDetails;
use App\Modules\Orders\Domain\Exceptions\EmptyCartException;
use App\Modules\Orders\Domain\Exceptions\OfferUnavailableException;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlaceOrderAction
{
    public function __construct(
        private readonly CheckoutGatewayInterface $catalog,
    ) {}

    /**
     * @param  list<CheckoutItemInput>  $items
     */
    public function execute(array $items, DeliveryDetails $delivery, ?int $customerId = null): Order
    {
        if ($items === []) {
            throw EmptyCartException::create();
        }

        // Resolve every offer up front so we fail on an unknown offer before
        // touching the database, and so all line snapshots share one currency.
        $lines = [];
        $currency = null;

        foreach ($items as $item) {
            $offer = $this->catalog->find($item->offerId);

            if (! $offer) {
                throw OfferUnavailableException::notFound($item->offerId);
            }

            $currency ??= $offer->currency->value;

            $lines[] = [
                'offer_id' => $offer->offerId,
                'vendor_id' => $offer->vendorId,
                'product_name' => $offer->productName,
                'brand' => $offer->brand,
                'unit_price_amount' => $offer->priceAmount,
                'currency' => $offer->currency->value,
                'quantity' => $item->quantity,
                'line_total_amount' => $offer->priceAmount * $item->quantity,
            ];
        }

        $subtotal = array_sum(array_column($lines, 'line_total_amount'));

        return DB::transaction(function () use ($lines, $delivery, $subtotal, $currency, $customerId) {
            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customerId,
                'customer_name' => $delivery->customerName,
                'customer_phone' => $delivery->customerPhone,
                'delivery_address' => $delivery->address,
                'delivery_city' => $delivery->city,
                'notes' => $delivery->notes,
                'payment_method' => 'cod',
                'status' => OrderStatus::Pending,
                'subtotal_amount' => $subtotal,
                'currency' => $currency,
            ]);

            // One sub-order per seller (vendor_id null = Technilium's own
            // stock), each with its own status and delivery (cahier §3).
            $groups = [];
            foreach ($lines as $line) {
                $groups[$line['vendor_id'] ?? 'own'][] = $line;
            }

            foreach ($groups as $group) {
                $subOrder = $order->subOrders()->create([
                    'vendor_id' => $group[0]['vendor_id'],
                    'status' => OrderStatus::Pending,
                    'subtotal_amount' => array_sum(array_column($group, 'line_total_amount')),
                    'currency' => $currency,
                ]);

                $subOrder->items()->createMany(array_map(fn (array $line) => $line + ['order_id' => $order->id], $group));
            }

            foreach ($lines as $line) {
                try {
                    $this->catalog->decrementStock($line['offer_id'], $line['quantity']);
                } catch (InsufficientStockException) {
                    throw OfferUnavailableException::insufficientStock($line['offer_id']);
                }
            }

            return $order->load('items', 'subOrders');
        });
    }

    private function generateOrderNumber(): string
    {
        return 'TCH-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
