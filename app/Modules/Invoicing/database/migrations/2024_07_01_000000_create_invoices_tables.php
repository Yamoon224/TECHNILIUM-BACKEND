<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['type', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('type'); // invoice|credit_note

            // No FK: Orders owns its table — cross-module links stay
            // application-level (cahier §9.1).
            $table->string('order_number');
            // Set on a credit note only: the invoice it cancels. Unique, so
            // an invoice can be credited at most once.
            $table->unsignedBigInteger('credited_invoice_id')->nullable()->unique();

            $table->string('customer_name');
            $table->char('currency', 3)->default('XOF');
            $table->unsignedInteger('vat_basis_points');
            // Credit notes carry positive amounts; their type conveys the sign.
            $table->unsignedInteger('net_amount');
            $table->unsignedInteger('vat_amount');
            $table->unsignedInteger('total_amount');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index('order_number');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_amount');
            $table->unsignedInteger('line_total_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
