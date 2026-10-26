<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Factories;

use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $delta = fake()->numberBetween(1, 20);

        return [
            'offer_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'created_by' => null,
            'quantity_delta' => $delta,
            'quantity_after' => $delta,
            'reason' => 'Réception fournisseur',
        ];
    }
}
