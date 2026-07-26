<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloth_categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('garments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('cloth_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
