<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id'); // no FK: Vendors owns its table (cahier §9.1)
            $table->string('vendor_name'); // snapshot for statements
            $table->string('status')->default('pending'); // pending|paid
            $table->char('currency', 3)->default('XOF');
            $table->unsignedSmallInteger('commission_basis_points');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('payouts');
            $table->unsignedBigInteger('vendor_id');
            $table->string('order_number');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');

            // A vendor's share of an order is paid at most once, even under
            // concurrent payout runs (cahier §7 idempotence).
            $table->unique(['vendor_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('payouts');
    }
};
