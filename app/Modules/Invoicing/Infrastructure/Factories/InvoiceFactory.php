<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Infrastructure\Factories;

use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'number' => 'FAC-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'type' => InvoiceType::Invoice,
            'order_number' => 'TCH-'.fake()->numerify('########'),
            'customer_name' => fake()->name(),
            'currency' => 'XOF',
            'vat_basis_points' => 1800,
            'net_amount' => 100_000,
            'vat_amount' => 18_000,
            'total_amount' => 118_000,
            'issued_at' => now(),
        ];
    }
}
