<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body shared by the staff status / priority / assignment endpoints. */
class StaffTicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            // true = take the ticket, false = release it.
            'assigned_to_me' => ['nullable', 'boolean'],
        ];
    }
}
