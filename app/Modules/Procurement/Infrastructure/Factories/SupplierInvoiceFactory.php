<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Factories;

use App\Modules\Procurement\Infrastructure\Models\Supplier;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'number' => 'F-'.fake()->unique()->numerify('#####'),
            'kind' => 'goods',
            'amount' => 100_000,
            'paid_amount' => 0,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ];
    }
}
