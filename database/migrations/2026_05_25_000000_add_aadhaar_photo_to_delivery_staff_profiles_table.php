<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_staff_profiles', function (Blueprint $table) {
            $table->string('aadhaar_photo')->nullable()->after('aadhaar_number');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_staff_profiles', function (Blueprint $table) {
            $table->dropColumn('aadhaar_photo');
        });
    }
};
