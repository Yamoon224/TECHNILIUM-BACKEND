<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // No FK: Catalog owns products, Identity owns users, Orders owns orders (cahier §9.1).
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('customer_id');
            // The delivered order that proves the purchase ("avis vérifié").
            $table->string('order_number');

            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            // Shown publicly, e.g. "Awa D." - a snapshot, never the full name.
            $table->string('author_name');

            $table->string('status')->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // One review per customer and product (cahier §5.9: avis authentiques).
            $table->unique(['product_id', 'customer_id']);
            $table->index(['product_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
