<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            // No FK: Identity owns users, Orders owns orders (cahier §9.1).
            $table->unsignedBigInteger('customer_id');
            $table->string('subject');
            $table->string('category')->default('other');
            $table->string('order_number')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();

            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->string('author_type'); // customer|staff
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            // Staff-only note: never shown to the customer.
            $table->boolean('internal')->default(false);
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
