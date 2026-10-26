<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Exceptions;

use App\Modules\Payments\Domain\PaymentStatus;
use DomainException;

final class InvalidPaymentTransitionException extends DomainException
{
    public static function from(PaymentStatus $current, PaymentStatus $target): self
    {
        return new self(sprintf(
            'Cannot transition payment from "%s" to "%s".',
            $current->value,
            $target->value,
        ));
    }
}
