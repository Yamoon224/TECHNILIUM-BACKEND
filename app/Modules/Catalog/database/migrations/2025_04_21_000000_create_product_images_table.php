<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // on the public disk
            $table->unsignedSmallInteger('position')->default(0); // 0 = cover
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        // The single photo of the previous design becomes the first (cover) image.
        DB::table('products')->whereNotNull('image_path')->orderBy('id')->each(function ($product) {
            DB::table('product_images')->insert([
                'product_id' => $product->id,
                'path' => $product->image_path,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });

        DB::table('product_images')->where('position', 0)->orderBy('id')->each(function ($image) {
            DB::table('products')->where('id', $image->product_id)->update(['image_path' => $image->path]);
        });

        Schema::dropIfExists('product_images');
    }
};
