<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->insert([
            ['code' => '311', 'name' => 'Marchandises en stock', 'type' => 'asset'],
            ['code' => '521', 'name' => 'Banque', 'type' => 'asset'],
            ['code' => '4012', 'name' => 'Fournisseurs de marchandises', 'type' => 'liability'],
            ['code' => '4013', 'name' => "Dettes de frais d'approche", 'type' => 'liability'],
            ['code' => '601', 'name' => 'Achats de marchandises vendues (coût des ventes)', 'type' => 'expense'],
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['311', '521', '4012', '4013', '601'])->delete();
    }
};
