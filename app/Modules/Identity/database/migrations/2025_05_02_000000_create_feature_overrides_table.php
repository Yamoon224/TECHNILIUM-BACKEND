<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A feature is on for everyone by default; a row switches it on or off for one group or one user.
        Schema::create('feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 64);
            $table->string('subject_type', 8); // role | user
            $table->string('subject'); // the role name, or the user id
            $table->boolean('enabled');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['feature', 'subject_type', 'subject']);
            $table->index(['subject_type', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_overrides');
    }
};
