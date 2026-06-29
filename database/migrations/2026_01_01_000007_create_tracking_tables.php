<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_staff_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete(); // role=delivery_staff
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at');
            $table->timestamps();
            
            $table->index('staff_id');
        });

        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();
            // Polymorphic to track status of orders, appointment, etc.
            $table->morphs('loggable'); 
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Who changed it
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
        Schema::dropIfExists('delivery_staff_locations');
    }
};
