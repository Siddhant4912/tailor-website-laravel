<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->decimal('cloth_length', 8, 2)->nullable()->after('secondary_price');
        });
    }

    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropColumn('cloth_length');
        });
    }
};
