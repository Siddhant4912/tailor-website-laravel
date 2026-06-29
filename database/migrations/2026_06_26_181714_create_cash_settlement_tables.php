<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add inclusive option to GST settings
        if (!Schema::hasColumn('gst_settings', 'is_inclusive')) {
            Schema::table('gst_settings', function (Blueprint $table) {
                $table->boolean('is_inclusive')->default(false);
            });
        }

        // 2. Create cash_settlements table
        Schema::create('cash_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('expected_amount', 10, 2);
            $table->decimal('submitted_amount', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->string('status')->default('pending'); // settled, mismatch, pending
            $table->text('remarks')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        // 3. Create cash_collections table (when staff collects cash from customer)
        Schema::create('cash_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('collectible'); // Order or Appointment
            $table->decimal('amount_collected', 10, 2);
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('settlement_id')->nullable()->constrained('cash_settlements')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Create payment_audit_logs table (immutable log)
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('loggable_type')->nullable();
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->string('type'); // collection, settlement, correction
            $table->decimal('amount_collected', 10, 2)->default(0);
            $table->decimal('amount_submitted', 10, 2)->default(0);
            $table->string('status');
            $table->text('admin_verification_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
        Schema::dropIfExists('cash_collections');
        Schema::dropIfExists('cash_settlements');
        if (Schema::hasColumn('gst_settings', 'is_inclusive')) {
            Schema::table('gst_settings', function (Blueprint $table) {
                $table->dropColumn('is_inclusive');
            });
        }
    }
};
