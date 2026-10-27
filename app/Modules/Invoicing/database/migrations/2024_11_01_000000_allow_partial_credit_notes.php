<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An invoice may now be credited several times (partial refunds);
        // the cap is enforced in IssueCreditNoteAction under a row lock.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['credited_invoice_id']);
            $table->index('credited_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['credited_invoice_id']);
            $table->unique('credited_invoice_id');
        });
    }
};
