<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            
            // Replaced visit_by with assigned_staff_id linked to users table (role = delivery_staff)
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['catalog_visit', 'custom_cloth']);
            
            $table->date('appointment_date');
            $table->time('appointment_time');
            
            // Full structured address
            $table->text('address_line');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();

            $table->string('status')->default('pending')->index(); // Changed to string for PHP 8 Enums
            
            $table->decimal('visit_charge', 10, 2)->default(0);
            $table->string('payment_status')->default('pending')->index();

            // Visit tracking
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_visited')->default(false);
            $table->boolean('measurement_taken')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('garment_id')->constrained('garments')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('appointment_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('file_path'); // renamed from image_path to be generic
            $table->string('file_type')->default('image');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_uploads');
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('appointments');
    }
};
