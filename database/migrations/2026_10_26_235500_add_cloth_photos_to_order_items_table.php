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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('old_cloth_photo')->nullable()->after('custom_notes');
            $table->string('new_cloth_photo')->nullable()->after('old_cloth_photo');
            $table->json('clothes')->nullable()->after('new_cloth_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['old_cloth_photo', 'new_cloth_photo', 'clothes']);
        });
    }
};
