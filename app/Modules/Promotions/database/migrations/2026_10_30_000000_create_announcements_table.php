<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // internal name, also the image's alt text
            $table->string('path'); // image, on the public disk
            $table->string('link_url')->nullable();
            $table->unsignedSmallInteger('width')->default(600); // px, the pop-up's size
            $table->unsignedSmallInteger('height')->default(400);
            $table->unsignedSmallInteger('delay_seconds')->default(3); // before it appears on the storefront
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // after this the pop-up is no longer shown
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
