<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();

            // Guest checkout supported (cahier §5.1) — no required FK to users.
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->string('delivery_city')->nullable();
            $table->text('notes')->nullable();

            $table->string('payment_method')->default('cod'); // cod = paiement à la livraison
            $table->string('status')->default('pending');

            $table->unsignedInteger('subtotal_amount');
            $table->char('currency', 3)->default('XOF');

            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
