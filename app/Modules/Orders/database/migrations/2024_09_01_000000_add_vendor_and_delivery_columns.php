<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Snapshot of the offer's vendor (null = Technilium's own stock).
            // Groups lines into per-vendor sub-orders; no FK (cahier §9.1).
            $table->unsignedBigInteger('vendor_id')->nullable()->after('offer_id');
            $table->index('vendor_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivered_at'));
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }
};
