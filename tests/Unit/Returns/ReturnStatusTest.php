<?php

declare(strict_types=1);

use App\Modules\Returns\Domain\ReturnStatus;

it('allows exactly the RMA transitions of the lifecycle', function (ReturnStatus $from, ReturnStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [ReturnStatus::Requested, ReturnStatus::Accepted, true],
    [ReturnStatus::Requested, ReturnStatus::Rejected, true],
    [ReturnStatus::Requested, ReturnStatus::Received, false],
    [ReturnStatus::Requested, ReturnStatus::Refunded, false],
    [ReturnStatus::Accepted, ReturnStatus::Received, true],
    [ReturnStatus::Accepted, ReturnStatus::Refunded, false],
    [ReturnStatus::Received, ReturnStatus::Refunded, true],
    [ReturnStatus::Received, ReturnStatus::Rejected, true],
    [ReturnStatus::Refunded, ReturnStatus::Rejected, false],
    [ReturnStatus::Rejected, ReturnStatus::Accepted, false],
]);

it('treats only undecided returns as open', function () {
    expect(ReturnStatus::Requested->isOpen())->toBeTrue()
        ->and(ReturnStatus::Accepted->isOpen())->toBeTrue()
        ->and(ReturnStatus::Received->isOpen())->toBeTrue()
        ->and(ReturnStatus::Refunded->isOpen())->toBeFalse()
        ->and(ReturnStatus::Rejected->isOpen())->toBeFalse();
});
