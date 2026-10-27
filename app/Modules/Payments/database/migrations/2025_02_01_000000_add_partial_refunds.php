<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Running total of refunds; the payment turns "refunded" only
            // once it reaches amount_amount.
            $table->unsignedBigInteger('refunded_amount')->default(0);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments');
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('refunded_amount'));
    }
};
