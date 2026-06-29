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
        Schema::table('garments', function (Blueprint $table) {
            if (Schema::hasColumn('garments', 'image')) {
                $table->dropColumn('image');
            }
            $table->text('images')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropColumn('images');
            $table->string('image')->nullable()->after('description');
        });
    }
};
