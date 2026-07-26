<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('building_name')->nullable()->after('appointment_time');
            $table->string('flat_number')->nullable()->after('building_name');
            $table->string('wing')->nullable()->after('flat_number');
            $table->string('street')->nullable()->after('wing');
            $table->string('locality')->nullable()->after('street');
            $table->string('landmark')->nullable()->after('locality');
            $table->string('district')->nullable()->after('city');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('building_name')->nullable()->after('visit_charge');
            $table->string('flat_number')->nullable()->after('building_name');
            $table->string('wing')->nullable()->after('flat_number');
            $table->string('street')->nullable()->after('wing');
            $table->string('locality')->nullable()->after('street');
            $table->string('landmark')->nullable()->after('locality');
            $table->string('city')->nullable()->after('landmark');
            $table->string('district')->nullable()->after('city');
            $table->string('state')->nullable()->after('district');
            $table->string('pincode')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'building_name',
                'flat_number',
                'wing',
                'street',
                'locality',
                'landmark',
                'district',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'building_name',
                'flat_number',
                'wing',
                'street',
                'locality',
                'landmark',
                'city',
                'district',
                'state',
                'pincode',
            ]);
        });
    }
};
