<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Informational: the lines are at list price while total_amount (and
        // its VAT split) is what the customer actually owed after the coupon.
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('coupon_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'coupon_code']));
    }
};
