<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_log', function (Blueprint $table) {
            // Language the message was written in (fr|en); French for messages sent before this existed.
            $table->string('locale', 5)->default('fr')->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_log', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
