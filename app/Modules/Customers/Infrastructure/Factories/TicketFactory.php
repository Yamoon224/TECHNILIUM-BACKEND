<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Factories;

use App\Modules\Customers\Domain\TicketCategory;
use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'customer_id' => fake()->numberBetween(1, 1000),
            'subject' => fake()->sentence(4),
            'category' => TicketCategory::Other,
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Open,
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
