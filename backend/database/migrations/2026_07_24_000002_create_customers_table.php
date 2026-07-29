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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 50)->unique()->index();
            $table->text('address')->nullable();
            $table->enum('nationality_type', ['WNI', 'WNA'])->default('WNI');
            $table->enum('identity_type', ['NIK', 'PASSPORT'])->default('NIK');
            $table->string('identity_number', 50)->nullable()->index();
            $table->string('country_origin', 100)->nullable();
            $table->string('identity_photo_path', 255)->nullable();
            $table->enum('identity_verification_status', ['UNVERIFIED', 'VERIFIED', 'EXPIRED'])->default('UNVERIFIED');
            $table->integer('total_bookings')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0.00);
            $table->dateTime('last_booking_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
