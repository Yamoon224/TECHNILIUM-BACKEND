<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();

            // One message per (event, reference, channel): replaying an event
            // never texts the customer twice (cahier §7 idempotence).
            $table->string('dedupe_key')->unique();
            $table->string('event'); // order.placed, order.shipped, return.refunded...
            $table->string('channel'); // sms|whatsapp
            $table->string('recipient');
            $table->text('body');
            $table->string('reference')->nullable(); // order number
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
