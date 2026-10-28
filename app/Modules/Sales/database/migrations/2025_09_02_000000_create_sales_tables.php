<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            // The cashier who counted the drawer (no FK: Identity owns users).
            $table->unsignedBigInteger('user_id');
            $table->string('user_name'); // snapshot: the report must not change if the account is renamed
            $table->char('currency', 3)->default('XOF');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('closed_at');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('cancellations_count')->default(0);
            $table->bigInteger('cash_sales')->default(0);
            $table->bigInteger('cash_refunds')->default(0);
            $table->bigInteger('expected_cash')->default(0);
            $table->bigInteger('counted_cash')->default(0);
            // counted - expected: negative = missing money, positive = surplus.
            $table->bigInteger('variance')->default(0);
            $table->bigInteger('mobile_money_net')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // What the till knows about each counter sale: who rang it, how it was paid, which
        // closing counted it, and whether it was cancelled (and in which closing the refund fell).
        Schema::create('internal_sales', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('method');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedBigInteger('closing_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedBigInteger('cancel_closing_id')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'closing_id']);
            $table->index(['cancelled_by', 'cancel_closing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_sales');
        Schema::dropIfExists('cash_closings');
    }
};
