<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Domain\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransitionOrderStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(OrderStatus::class)],
        ];
    }

    public function targetStatus(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
