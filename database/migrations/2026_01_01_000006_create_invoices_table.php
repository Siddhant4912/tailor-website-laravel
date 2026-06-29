<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate', 5, 2); // e.g., 18.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            
            // Polymorphic relation to allow an invoice for either an appointment or an order
            $table->nullableMorphs('invoiceable'); 
            // Creates invoiceable_id and invoiceable_type
            
            $table->string('invoice_number')->unique();
            
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('visit_charge', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            
            $table->string('status')->default('generated')->index();
            
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('transaction_number')->unique()->nullable(); // from payment gateway
            $table->string('payment_mode')->default('cash'); // cash, online, upi, card
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending')->index(); // pending, successful, failed
            $table->text('gateway_response')->nullable(); // to store raw gateway data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('gst_settings');
    }
};
