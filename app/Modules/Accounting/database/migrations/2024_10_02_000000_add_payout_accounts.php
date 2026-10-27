<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->insert([
            ['code' => '4011', 'name' => 'Dettes envers les vendeurs', 'type' => 'liability'],
            ['code' => '706', 'name' => 'Commissions marketplace', 'type' => 'revenue'],
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['4011', '706'])->delete();
    }
};
