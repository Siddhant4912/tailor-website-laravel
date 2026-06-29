<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            $table->string('order_number')->unique();
            
            // One order belongs to one appointment ideally.
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            // User who placed it
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            
            // Staff assignments
            $table->foreignId('pickup_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('visit_charge', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);

            $table->date('delivery_date')->nullable();
            
            $table->string('status')->default('pending')->index(); // Enum casting

            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            
            // Prevent duplicate orders for a single appointment if it exists
            $table->unique('appointment_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('garment_id')->nullable()->constrained('garments')->nullOnDelete();
            $table->string('garment_name'); // Snapshot
            
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            
            $table->foreignId('assigned_tailor_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('status')->default('pending')->index(); // Item level status
            $table->timestamps();
        });

        Schema::create('order_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('field_name');
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_measurements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
