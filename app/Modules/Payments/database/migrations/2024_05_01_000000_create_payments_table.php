<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // No FK: Orders owns the orders table — cross-module references
            // stay application-level, never a SQL join (cahier §9.1).
            $table->string('order_number');

            $table->string('method'); // cod|mobile_money
            $table->string('status')->default('pending'); // pending|completed|failed|refunded

            $table->unsignedInteger('amount_amount');
            $table->char('currency', 3)->default('XOF');

            // Only set for mobile_money: which operator, and the
            // transaction reference used to reconcile it manually.
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
