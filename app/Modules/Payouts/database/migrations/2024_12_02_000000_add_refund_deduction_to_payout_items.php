<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Informational: how much of the vendor's share was withheld for
        // refunded returns. gross_amount is already net of it.
        Schema::table('payout_items', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_deduction_amount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', fn (Blueprint $table) => $table->dropColumn('refund_deduction_amount'));
    }
};
