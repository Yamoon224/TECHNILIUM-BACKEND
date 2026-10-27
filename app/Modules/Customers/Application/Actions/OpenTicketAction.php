<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Actions;

use App\Modules\Customers\Domain\Exceptions\TicketException;
use App\Modules\Customers\Domain\TicketCategory;
use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use Illuminate\Support\Facades\DB;

/**
 * A customer opens a support ticket, optionally about one of their orders.
 * The order is checked against the customer's account through Orders' public
 * contract: a ticket can never be attached to someone else's order.
 */
final class OpenTicketAction
{
    public function __construct(private readonly OrderLookupGatewayInterface $orders) {}

    public function execute(
        int $customerId,
        string $subject,
        TicketCategory $category,
        string $message,
        ?string $orderNumber = null,
    ): Ticket {
        if ($orderNumber !== null) {
            $order = $this->orders->find($orderNumber);

            // The same refusal whether the order is missing or someone else's.
            if ($order === null || $order->customerId !== $customerId) {
                throw TicketException::orderNotYours($orderNumber);
            }
        }

        return DB::transaction(function () use ($customerId, $subject, $category, $message, $orderNumber) {
            $ticket = Ticket::query()->create([
                'customer_id' => $customerId,
                'subject' => $subject,
                'category' => $category,
                'order_number' => $orderNumber,
                // The team re-prioritises; a customer cannot jump the queue.
                'priority' => TicketPriority::Normal,
                'status' => TicketStatus::Open,
            ]);

            $ticket->messages()->create([
                'author_type' => 'customer',
                'author_id' => $customerId,
                'body' => $message,
                'internal' => false,
            ]);

            return $ticket->load('messages');
        });
    }
}
