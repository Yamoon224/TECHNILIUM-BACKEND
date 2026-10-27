<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface;
use App\Modules\Shipping\Domain\Exceptions\DeliveryCodeException;
use App\Modules\Shipping\Domain\Exceptions\InvalidShipmentTransitionException;
use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Proof of handover (cahier §5.6): the courier types the code the customer
 * was given. Wrong codes are counted and the shipment locks after
 * MAX_CODE_ATTEMPTS, so a six-digit code cannot be brute-forced.
 */
final class ConfirmDeliveryAction
{
    public const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly OrderLookupGatewayInterface $orders,
        private readonly TransitionShipmentStatusAction $transition,
    ) {}

    public function execute(Shipment $shipment, string $code): Shipment
    {
        // Check and count under one row lock, so parallel guesses are
        // serialised and cannot slip past the limit. The outcome is returned
        // rather than thrown inside, because a throw would roll back the
        // attempt counter we just incremented.
        [$shipment, $attemptsLeft] = DB::transaction(function () use ($shipment, $code) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            // An illegal move is reported as such, without burning an attempt.
            if (! $locked->status->canTransitionTo(ShipmentStatus::Delivered)) {
                throw InvalidShipmentTransitionException::from($locked->status, ShipmentStatus::Delivered);
            }

            if ($locked->code_attempts >= self::MAX_CODE_ATTEMPTS) {
                return [$locked, 0];
            }

            if ($this->orders->confirmDeliveryCode($locked->order_number, $code)) {
                return [$locked, null];
            }

            $locked->increment('code_attempts');

            return [$locked, max(0, self::MAX_CODE_ATTEMPTS - $locked->code_attempts)];
        });

        if ($attemptsLeft === 0) {
            throw DeliveryCodeException::locked();
        }

        if ($attemptsLeft !== null) {
            throw DeliveryCodeException::wrong($attemptsLeft);
        }

        return $this->transition->execute($shipment, ShipmentStatus::Delivered);
    }
}
