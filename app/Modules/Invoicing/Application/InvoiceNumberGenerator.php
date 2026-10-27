<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application;

use App\Modules\Invoicing\Domain\InvoiceType;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential numbering per document type and fiscal year
 * (cahier §6.7). Must run inside the caller's transaction so a rolled-back
 * document does not burn a number; the row lock serialises concurrent issuers.
 */
class InvoiceNumberGenerator
{
    public function next(InvoiceType $type, int $year): string
    {
        $row = DB::table('invoice_sequences')
            ->where('type', $type->value)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            DB::table('invoice_sequences')->insert([
                'type' => $type->value,
                'year' => $year,
                'last_number' => 1,
            ]);
            $number = 1;
        } else {
            $number = $row->last_number + 1;
            DB::table('invoice_sequences')->where('id', $row->id)->update(['last_number' => $number]);
        }

        return sprintf('%s-%d-%06d', $type->prefix(), $year, $number);
    }
}
