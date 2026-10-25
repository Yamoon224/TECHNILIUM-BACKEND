<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('brand');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Per-category technical specs (screen, RAM, storage...) feeding the
            // comparator/facets (cahier §6.1 "modèles d'attributs par catégorie").
            $table->json('attributes')->nullable();

            $table->string('condition_state')->default('neuf'); // neuf | reconditionne | occasion
            $table->string('status')->default('draft'); // draft | published | archived
            $table->timestamps();

            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
