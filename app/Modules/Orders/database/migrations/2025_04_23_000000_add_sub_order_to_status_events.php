<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_events', function (Blueprint $table) {
            // Null = the parent order's own history; set = one seller's parcel.
            $table->foreignId('sub_order_id')->nullable()->after('order_id')->constrained()->cascadeOnDelete();
        });

        // Existing parcels: when they were created, and when they reached
        // their current status (delivery date when delivered, else last update).
        DB::table('sub_orders')->orderBy('id')->each(function ($subOrder) {
            $rows = [['status' => 'pending', 'occurred_at' => $subOrder->created_at]];

            if ($subOrder->status !== 'pending') {
                $rows[] = ['status' => $subOrder->status, 'occurred_at' => $subOrder->delivered_at ?? $subOrder->updated_at];
            }

            foreach ($rows as $row) {
                DB::table('order_status_events')->insert($row + [
                    'order_id' => $subOrder->order_id,
                    'sub_order_id' => $subOrder->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_status_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_order_id');
        });
    }
};
