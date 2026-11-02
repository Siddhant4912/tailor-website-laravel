<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            $table->dropForeign(['garment_id']);
            $table->unsignedBigInteger('garment_id')->nullable()->change();
            $table->foreign('garment_id')->references('id')->on('garments')->cascadeOnDelete();
            if (!Schema::hasColumn('appointment_items', 'custom_name')) {
                $table->string('custom_name')->nullable()->after('garment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            if (Schema::hasColumn('appointment_items', 'custom_name')) {
                $table->dropColumn('custom_name');
            }
            $table->unsignedBigInteger('garment_id')->nullable(false)->change();
        });
    }
};
