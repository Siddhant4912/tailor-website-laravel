<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('building_name')->nullable()->after('user_id');
            $table->string('flat_number')->nullable()->after('building_name');
            $table->string('wing')->nullable()->after('flat_number');
            $table->string('street')->nullable()->after('wing');
            $table->string('locality')->nullable()->after('street');
            $table->string('landmark')->nullable()->after('locality');
            $table->string('district')->nullable()->after('city');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('building_name')->nullable()->after('phone');
            $table->string('flat_number')->nullable()->after('building_name');
            $table->string('wing')->nullable()->after('flat_number');
            $table->string('street')->nullable()->after('wing');
            $table->string('locality')->nullable()->after('street');
            $table->string('landmark')->nullable()->after('locality');
            $table->string('district')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['building_name', 'flat_number', 'wing', 'street', 'locality', 'landmark', 'district']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['building_name', 'flat_number', 'wing', 'street', 'locality', 'landmark', 'district']);
        });
    }
};
