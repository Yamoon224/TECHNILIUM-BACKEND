<?php

declare(strict_types=1);

use App\Modules\Procurement\Domain\AverageCost;
use App\Modules\Procurement\Domain\LandedCostAllocator;
use App\Modules\Procurement\Domain\PurchaseOrderStatus;

it('allocates landed costs pro rata to the value received', function () {
    // 10 000 of landed costs on 100 000 of goods: receiving half carries half.
    expect(LandedCostAllocator::shareForReceipt(10_000, 100_000, 50_000, 0))->toBe(5_000);
});

it('makes partial receipts add up to exactly the landed total, whatever the rounding', function (array $receivedValues, int $landed, int $goods) {
    $received = 0;
    $allocated = 0;

    foreach ($receivedValues as $value) {
        $received += $value;
        $allocated += LandedCostAllocator::shareForReceipt($landed, $goods, $received, $allocated);
    }

    expect($allocated)->toBe($landed);
})->with([
    'thirds of an awkward total' => [[33_333, 33_333, 33_334], 10_000, 100_000],
    'many small receipts' => [array_fill(0, 7, 14_286), 9_999, 100_002],
    'uneven' => [[1, 99_998, 1], 7, 100_000],
]);

it('allocates the whole amount once everything is received', function () {
    expect(LandedCostAllocator::shareForReceipt(12_345, 80_000, 80_000, 0))->toBe(12_345)
        ->and(LandedCostAllocator::shareForReceipt(12_345, 80_000, 80_000, 6_000))->toBe(6_345);
});

it('catches up on the next receipt when a landed cost is added late', function () {
    // First receipt (half the goods) happened with NO landed cost on the order yet.
    $first = LandedCostAllocator::shareForReceipt(0, 100_000, 50_000, 0);
    // The 10 000 cost is then recorded; the second receipt completes the order and must carry ALL of it.
    $second = LandedCostAllocator::shareForReceipt(10_000, 100_000, 100_000, $first);

    expect($first)->toBe(0)
        ->and($second)->toBe(10_000)
        ->and($first + $second)->toBe(10_000);
});

it('allocates nothing without costs or goods, never more than the total, and never a negative share', function () {
    expect(LandedCostAllocator::shareForReceipt(0, 100_000, 50_000, 0))->toBe(0)
        ->and(LandedCostAllocator::shareForReceipt(5_000, 0, 0, 0))->toBe(0)
        ->and(LandedCostAllocator::shareForReceipt(5_000, 10_000, 999_999, 0))->toBe(5_000)
        // A later receipt that is owed less than already carried (a cost was removed): zero, not negative.
        ->and(LandedCostAllocator::shareForReceipt(1_000, 10_000, 5_000, 900))->toBe(0);
});

it('splits a receipt\'s share across lines by value, the last line taking the remainder', function () {
    $parts = LandedCostAllocator::splitAcrossLines(10, [1, 1, 1]);

    expect(array_sum($parts))->toBe(10)->and($parts)->toHaveCount(3);
    expect(LandedCostAllocator::splitAcrossLines(1_000, [750, 250]))->toBe([750, 250]);
    expect(LandedCostAllocator::splitAcrossLines(0, [5, 5]))->toBe([0, 0]);
    expect(LandedCostAllocator::splitAcrossLines(100, [0, 0]))->toBe([0, 0]);
});

it('averages cost across what is on hand and what arrives', function () {
    // 10 on hand at 100, 10 arrive costing 200 each (2 000 total) -> 150.
    expect(AverageCost::next(10, 100, 10, 2_000))->toBe(150);
});

it('takes the new cost alone when the stock on hand has no known cost', function () {
    expect(AverageCost::next(0, 0, 10, 2_000))->toBe(200)
        ->and(AverageCost::next(5, 0, 10, 2_000))->toBe(200)    // stock never purchased here: no phantom zero
        ->and(AverageCost::next(-3, 100, 10, 2_000))->toBe(200);
});

it('rounds the average half up and keeps the old one when nothing arrives', function () {
    // (1 × 100 + 100) / 2 = 100 ; (1 × 100 + 101) / 2 = 100.5 -> 101
    expect(AverageCost::next(1, 100, 1, 101))->toBe(101)
        ->and(AverageCost::next(10, 150, 0, 0))->toBe(150);
});

it('defines the purchase order lifecycle', function (PurchaseOrderStatus $from, PurchaseOrderStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered, true],
    [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Received, false],
    [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Cancelled, true],
    [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived, true],
    [PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Cancelled, false],
    [PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received, true],
    [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled, false],
    [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Ordered, false],
]);

it('accepts receipts only on an order that was sent and is not complete', function () {
    expect(PurchaseOrderStatus::Draft->acceptsReceipts())->toBeFalse()
        ->and(PurchaseOrderStatus::Ordered->acceptsReceipts())->toBeTrue()
        ->and(PurchaseOrderStatus::PartiallyReceived->acceptsReceipts())->toBeTrue()
        ->and(PurchaseOrderStatus::Received->acceptsReceipts())->toBeFalse()
        ->and(PurchaseOrderStatus::Cancelled->acceptsReceipts())->toBeFalse();
});

it('derives the status from what is still outstanding', function () {
    expect(PurchaseOrderStatus::afterReceipt(0, 10))->toBe(PurchaseOrderStatus::Received)
        ->and(PurchaseOrderStatus::afterReceipt(4, 6))->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and(PurchaseOrderStatus::afterReceipt(10, 0))->toBe(PurchaseOrderStatus::Ordered);
});
