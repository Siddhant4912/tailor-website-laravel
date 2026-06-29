<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('measurement_fields') && !Schema::hasColumn('measurement_fields', 'display_name')) {
            Schema::table('measurement_fields', function (Blueprint $table) {
                $table->string('display_name')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('measurement_fields') && Schema::hasColumn('measurement_fields', 'display_name')) {
            Schema::table('measurement_fields', function (Blueprint $table) {
                $table->dropColumn('display_name');
            });
        }
    }
};
