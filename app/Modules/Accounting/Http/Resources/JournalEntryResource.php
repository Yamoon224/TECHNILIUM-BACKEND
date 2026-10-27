<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JournalEntry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'journal' => $entry->journal,
            'entry_date' => $entry->entry_date->toDateString(),
            'description' => $entry->description,
            'reference' => $entry->reference,
            'currency' => $entry->currency,
            'lines' => $entry->lines->map(fn ($l) => [
                'account' => $l->account_code,
                'debit' => $l->debit,
                'credit' => $l->credit,
            ])->all(),
        ];
    }
}
