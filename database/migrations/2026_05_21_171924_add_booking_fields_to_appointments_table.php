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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('gender')->default('female')->after('type');
            $table->string('female_tailor_visit')->nullable()->after('gender'); // 'yes', 'no'
            $table->string('has_fabric')->default('yes')->after('female_tailor_visit'); // 'yes', 'no'
            $table->string('measurement_type')->nullable()->after('has_fabric'); // 'onsite_visit', 'existing_garment'
            
            $table->decimal('deposit_amount', 8, 2)->default(0.00)->after('visit_charge');
            $table->decimal('cloth_advance_amount', 8, 2)->default(0.00)->after('deposit_amount');
            $table->decimal('cloth_total_amount', 8, 2)->default(0.00)->after('cloth_advance_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'female_tailor_visit',
                'has_fabric',
                'measurement_type',
                'deposit_amount',
                'cloth_advance_amount',
                'cloth_total_amount',
            ]);
        });
    }
};