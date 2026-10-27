<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // stored upper-case
            $table->string('type'); // percent|fixed
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('min_order_amount')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons');
            // No FK: Orders owns its table (cahier §9.1).
            $table->string('order_number')->unique();
            // Who used it, for the per-customer limit (the checkout phone).
            $table->string('customer_key');
            $table->unsignedBigInteger('discount_amount');
            $table->timestamps();

            $table->index(['coupon_id', 'customer_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
