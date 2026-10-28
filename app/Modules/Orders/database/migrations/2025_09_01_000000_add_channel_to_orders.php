<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // "online" = placed on the storefront; "internal" = a counter sale rung up by staff.
            $table->string('channel')->default('online');
            // The staff member who rang up an internal sale (no FK: Identity owns users).
            $table->unsignedBigInteger('sold_by')->nullable();
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['channel', 'sold_by']));
    }
};
