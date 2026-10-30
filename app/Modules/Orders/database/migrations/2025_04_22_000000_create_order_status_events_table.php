<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // the order's (parent) status it moved to
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
        });

        // Orders that already exist: only what is known - when they were placed,
        // and when they reached their current status (delivery date when delivered,
        // else the last update). Earlier intermediate steps cannot be recovered.
        DB::table('orders')->orderBy('id')->each(function ($order) {
            $rows = [['order_id' => $order->id, 'status' => 'pending', 'occurred_at' => $order->created_at]];

            if ($order->status !== 'pending') {
                $rows[] = ['order_id' => $order->id, 'status' => $order->status, 'occurred_at' => $order->delivered_at ?? $order->updated_at];
            }

            foreach ($rows as $row) {
                DB::table('order_status_events')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
    }
};
