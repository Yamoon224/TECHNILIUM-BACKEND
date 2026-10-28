<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_sales', function (Blueprint $table) {
            // Whose drawer the refund comes out of: the cancelling cashier, or the seller when
            // management cancels remotely. cancelled_by stays the person who actually decided.
            $table->unsignedBigInteger('refund_till_user_id')->nullable();
            $table->index(['refund_till_user_id', 'cancel_closing_id']);
        });

        DB::table('internal_sales')->whereNotNull('cancelled_at')->update(['refund_till_user_id' => DB::raw('cancelled_by')]);
    }

    public function down(): void
    {
        Schema::table('internal_sales', fn (Blueprint $table) => $table->dropColumn('refund_till_user_id'));
    }
};
