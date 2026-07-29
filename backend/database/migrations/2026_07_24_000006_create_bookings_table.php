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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('booking_type', ['package', 'car_rental']);
            $table->string('item_name', 255);
            $table->dateTime('date');
            $table->string('duration', 100);
            $table->text('notes')->nullable();
            $table->decimal('total_price', 15, 2);
            $table->enum('payment_type', ['FULL', 'DP'])->default('FULL');
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('remaining_balance', 15, 2)->default(0.00);
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'expired'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'pending', 'partially_paid', 'paid', 'failed', 'expired'])->default('unpaid');
            $table->string('order_id', 100)->nullable();
            $table->string('snap_token', 255)->nullable();
            $table->text('payment_link')->nullable();
            $table->dateTime('expiry_time');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
