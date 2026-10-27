<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            // asset|liability|equity|expense|revenue
            $table->string('type');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal', 4); // VT ventes, CA caisse, MM mobile money
            $table->date('entry_date');
            $table->string('description');
            // Origin of the entry; unique so replaying an event (webhook,
            // retried job) can never post twice (cahier §7 idempotence).
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('reference')->nullable();
            $table->char('currency', 3)->default('XOF');
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->string('account_code');
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);

            $table->index('account_code');
        });

        // Default SYSCOHADA revised chart, limited to the accounts the
        // automatic posting schemes use so far (cahier §6.9).
        DB::table('accounts')->insert([
            ['code' => '411', 'name' => 'Clients', 'type' => 'asset'],
            ['code' => '571', 'name' => 'Caisse (paiements à la livraison)', 'type' => 'asset'],
            ['code' => '585', 'name' => 'Mobile money', 'type' => 'asset'],
            ['code' => '4431', 'name' => 'TVA facturée sur ventes', 'type' => 'liability'],
            ['code' => '701', 'name' => 'Ventes de marchandises', 'type' => 'revenue'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
