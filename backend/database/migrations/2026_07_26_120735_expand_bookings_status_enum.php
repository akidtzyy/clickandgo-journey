<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Expands the 'status' enum on the bookings table to include
     * 'rescheduled' and 'paid', and payment_status to include 'challenge'.
     */
    public function up(): void
    {
        // MySQL requires re-defining the full enum list to alter it
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','paid','completed','cancelled','rescheduled','expired') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE bookings MODIFY COLUMN payment_status ENUM('unpaid','pending','partially_paid','paid','failed','expired','challenge') NOT NULL DEFAULT 'unpaid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','expired') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE bookings MODIFY COLUMN payment_status ENUM('unpaid','pending','partially_paid','paid','failed','expired') NOT NULL DEFAULT 'unpaid'");
    }
};
