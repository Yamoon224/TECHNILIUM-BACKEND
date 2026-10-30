<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Visits on which the pop-up is shown to a visitor; null = until the visitor closes it.
            $table->unsignedSmallInteger('max_displays')->nullable()->after('delay_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('max_displays');
        });
    }
};
