<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who. No FK: Identity owns users, and an entry must outlive the account.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable(); // snapshot
            $table->string('roles')->nullable(); // snapshot, comma-separated

            // What.
            $table->string('method', 10);
            $table->string('path');
            $table->string('route')->nullable(); // pattern, e.g. api/v1/admin/payments/{payment}/refund
            $table->unsignedSmallInteger('status');
            $table->json('payload')->nullable(); // sanitised request body

            // From where / when.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');

            // Tamper evidence: signature chained to the previous entry.
            $table->string('previous_hash', 64);
            $table->string('hash', 64)->unique();

            $table->index('user_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
