<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // No FK: Orders owns the orders table, Identity owns the users
            // table - cross-module references stay application-level, never
            // a SQL join (cahier §9.1). One shipment per order.
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('assigned_to')->nullable();

            // Snapshotted from the order at creation (cahier's established
            // pattern - see order_items) so this stays accurate even if the
            // order's own delivery details are edited later.
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->string('delivery_city')->nullable();

            $table->string('status')->default('pending'); // pending|in_transit|delivered|failed_attempt|returned
            $table->unsignedInteger('attempts')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index('assigned_to');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
