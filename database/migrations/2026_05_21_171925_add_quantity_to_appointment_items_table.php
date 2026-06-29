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
        Schema::table('appointment_items', function (Blueprint $table) {
            // Adds the quantity column defaulting to 1 (useful for cart item alignment)
            if (!Schema::hasColumn('appointment_items', 'quantity')) {
                $table->integer('quantity')->default(1)->after('garment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            if (Schema::hasColumn('appointment_items', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
