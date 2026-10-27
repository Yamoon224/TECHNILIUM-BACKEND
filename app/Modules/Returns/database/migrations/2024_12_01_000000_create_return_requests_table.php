<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            // No FK: Orders owns its table (cahier §9.1).
            $table->string('order_number');
            $table->string('status')->default('requested');
            $table->string('reason');
            $table->text('decision_note')->nullable();

            $table->char('currency', 3)->default('XOF');
            // Amount the customer asks back / amount actually refunded.
            $table->unsignedBigInteger('requested_amount');
            $table->unsignedBigInteger('refunded_amount')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
