<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The cost of sales of an order, line by line, so a margin can be told
        // per product (the order_costs total alone cannot).
        Schema::create('order_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // no FK: Orders owns its table (cahier §9.1)
            $table->unsignedBigInteger('offer_id');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('cost_amount'); // quantity x average cost at delivery

            $table->unique(['order_id', 'offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cost_lines');
    }
};
