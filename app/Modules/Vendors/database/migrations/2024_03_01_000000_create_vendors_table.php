<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // No FK: Identity owns the users table — cross-module references
            // stay application-level, never a SQL join (cahier §9.1).
            $table->unsignedBigInteger('user_id')->unique();

            $table->string('company_name');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('status')->default('pending'); // pending|active|suspended

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
