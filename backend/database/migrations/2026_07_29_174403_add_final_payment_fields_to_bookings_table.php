<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('final_order_id', 100)->nullable()->after('order_id');
            $table->string('final_snap_token', 255)->nullable()->after('snap_token');
            $table->text('final_payment_link')->nullable()->after('payment_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['final_order_id', 'final_snap_token', 'final_payment_link']);
        });
    }
};
