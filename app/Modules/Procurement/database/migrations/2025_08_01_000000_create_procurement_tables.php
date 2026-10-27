<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            // Days after the invoice date by which it is due (cahier §6.4 "conditions de paiement").
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->unsignedSmallInteger('lead_time_days')->nullable(); // délai d'approvisionnement
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('status')->default('draft');
            $table->char('currency', 3)->default('XOF');
            $table->date('expected_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            // No FK: Catalog owns offers (cahier §9.1). Description is a snapshot.
            $table->unsignedBigInteger('offer_id');
            $table->string('description');
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0); // accepted into stock
            $table->unsignedInteger('quantity_rejected')->default(0); // refused at inspection, back to the supplier
            $table->unsignedBigInteger('unit_cost_amount');

            $table->unique(['purchase_order_id', 'offer_id']);
        });

        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('label'); // transport, douane, manutention...
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->unsignedBigInteger('goods_amount'); // accepted quantity x unit cost
            $table->unsignedBigInteger('landed_amount'); // landed costs allocated to this receipt
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts');
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines');
            $table->unsignedInteger('quantity_accepted');
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->unsignedBigInteger('landed_amount')->default(0); // this line's share of the receipt's landed costs
        });

        // Running weighted-average unit cost per own-stock offer (CMP).
        Schema::create('offer_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id')->primary();
            $table->unsignedBigInteger('average_cost_amount');
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->string('number'); // the supplier's own invoice number
            $table->string('kind')->default('goods'); // goods|landed_cost
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->timestamps();

            // The same supplier cannot bill the same number twice.
            $table->unique(['supplier_id', 'number']);
            $table->index('due_date');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices');
            $table->unsignedBigInteger('amount');
            $table->string('method'); // bank_transfer|mobile_money|cash|cheque
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'supplier_payments', 'supplier_invoices', 'offer_costs', 'goods_receipt_lines', 'goods_receipts',
            'landed_costs', 'purchase_order_lines', 'purchase_orders', 'suppliers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
