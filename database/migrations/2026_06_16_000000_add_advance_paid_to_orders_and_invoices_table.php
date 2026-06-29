<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('advance_paid', 10, 2)->default(0)->after('visit_charge');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('advance_paid', 10, 2)->default(0)->after('visit_charge');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('advance_paid');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('advance_paid');
        });
    }
};
