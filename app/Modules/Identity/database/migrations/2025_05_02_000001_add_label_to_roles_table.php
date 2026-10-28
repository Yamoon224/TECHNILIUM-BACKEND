<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custom user groups: a readable name and a description next to the slug.
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->string('label')->nullable();
            $table->string('description', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
