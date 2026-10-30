<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            // [{offer_id, quantity}] - optional: a return may be a pure
            // amount (goodwill) without naming articles.
            $table->json('items')->nullable();
            $table->boolean('restocked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', fn (Blueprint $table) => $table->dropColumn(['items', 'restocked']));
    }
};
