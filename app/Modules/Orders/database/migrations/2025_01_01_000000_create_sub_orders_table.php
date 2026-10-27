<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // The seller of this share; null = Technilium's own stock (1P).
            // No FK: Vendors owns its table (cahier §9.1).
            $table->unsignedBigInteger('vendor_id')->nullable();

            $table->string('status')->default('pending');
            $table->unsignedInteger('subtotal_amount');
            $table->char('currency', 3)->default('XOF');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_order_id')->nullable()->after('order_id');
            $table->index('sub_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['sub_order_id']);
            $table->dropColumn('sub_order_id');
        });
        Schema::dropIfExists('sub_orders');
    }
};
