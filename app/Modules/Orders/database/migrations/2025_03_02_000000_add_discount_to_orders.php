<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // subtotal_amount stays "what the customer owes" (after discount), so
        // payments, invoices, refunds and reports keep working unchanged.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('coupon_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'coupon_code']));
    }
};
