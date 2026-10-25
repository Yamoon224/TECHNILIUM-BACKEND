<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stop-gap stock counter living on the offer itself, just enough for the
 * storefront cart to respect availability. This is NOT the real inventory
 * model from cahier §6.3 (multi-entrepôt, numéros de série, réservations,
 * mouvements) — that belongs to the future Inventory module. When it lands,
 * this column should be replaced by a computed "disponible" read from there,
 * not kept as a second source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('warranty_months');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
