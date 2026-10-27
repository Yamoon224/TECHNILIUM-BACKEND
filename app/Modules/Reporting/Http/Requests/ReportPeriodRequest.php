<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ReportPeriodRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * The inclusive reporting window — the last 30 days when unspecified.
     *
     * @return array{Carbon, Carbon}
     */
    public function period(): array
    {
        $to = $this->filled('to') ? Carbon::parse($this->string('to')->toString())->endOfDay() : now()->endOfDay();
        $from = $this->filled('from') ? Carbon::parse($this->string('from')->toString())->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return [$from, $to];
    }

    protected function passedValidation(): void
    {
        [$from, $to] = $this->period();

        abort_if(
            $from->diffInDays($to) > (int) config('reporting.max_period_days'),
            422,
            'La période ne peut pas dépasser '.config('reporting.max_period_days').' jours.',
        );
    }
}
