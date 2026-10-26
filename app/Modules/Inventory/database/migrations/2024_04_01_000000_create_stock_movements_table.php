<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // No FK: Catalog owns the offers table, Identity owns the users
            // table — cross-module references stay application-level, never
            // a SQL join (cahier §9.1).
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->integer('quantity_delta'); // signed: + reçu, - retiré
            $table->unsignedInteger('quantity_after'); // snapshot post-mouvement
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
