<?php

declare(strict_types=1);

namespace App\Modules\Returns\Infrastructure\Factories;

use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    protected $model = ReturnRequest::class;

    public function definition(): array
    {
        return [
            'order_number' => 'TCH-'.fake()->numerify('########'),
            'status' => ReturnStatus::Requested,
            'reason' => 'Produit défectueux',
            'currency' => 'XOF',
            'requested_amount' => 10_000,
        ];
    }

    public function status(ReturnStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
