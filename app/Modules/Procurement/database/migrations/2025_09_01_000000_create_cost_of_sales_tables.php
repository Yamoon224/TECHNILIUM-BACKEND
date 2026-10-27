<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The cost of sales recognised when an order was delivered. It is what
        // a later customer return may give back — never more.
        Schema::create('order_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->primary(); // no FK: Orders owns its table (cahier §9.1)
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('cost_amount');
            $table->timestamps();
        });

        // One row per returned parcel that gave cost back, unique per return so
        // a replayed event cannot reverse the same cost twice.
        Schema::create('cost_reversals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_reversals');
        Schema::dropIfExists('order_costs');
    }
};
