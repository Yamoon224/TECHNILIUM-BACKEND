<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // No FK: Catalog owns the offers table — cross-module references
            // stay application-level (cahier §9.1). Pricing/name are snapshot
            // below so the order stays accurate even if the catalog changes later.
            $table->unsignedBigInteger('offer_id');

            $table->string('product_name');
            $table->string('brand');
            $table->unsignedInteger('unit_price_amount');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total_amount');

            $table->timestamps();

            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
