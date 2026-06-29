<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_measurements', function (Blueprint $table) {
            $table->foreignId('garment_id')
                ->nullable()
                ->after('order_id')
                ->constrained('garments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_measurements', function (Blueprint $table) {
            $table->dropForeign(['garment_id']);
            $table->dropColumn('garment_id');
        });
    }
};
