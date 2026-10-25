<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            // No FK: the Vendors module owns the vendors table — cross-module
            // references stay application-level, never a SQL join (cahier §9.1).
            // null => vente en propre (1P / Technilium).
            $table->unsignedBigInteger('vendor_id')->nullable();

            $table->unsignedInteger('price_amount'); // smallest currency unit
            $table->char('currency', 3)->default('XOF');
            $table->unsignedInteger('compare_at_price_amount')->nullable(); // "prix barré"

            $table->string('condition_grade')->nullable(); // grade for reconditionné/occasion
            $table->unsignedSmallInteger('warranty_months')->default(0);

            $table->string('status')->default('draft'); // draft|pending_review|published|rejected|archived
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['product_variant_id', 'status']);
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
