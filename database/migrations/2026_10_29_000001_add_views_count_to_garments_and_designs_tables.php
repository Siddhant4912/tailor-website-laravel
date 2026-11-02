<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('garments')) {
            Schema::table('garments', function (Blueprint $table) {
                if (!Schema::hasColumn('garments', 'views_count')) {
                    $table->unsignedBigInteger('views_count')->default(0)->after('price')->index();
                }
            });
        }

        if (Schema::hasTable('designs')) {
            Schema::table('designs', function (Blueprint $table) {
                if (!Schema::hasColumn('designs', 'views_count')) {
                    $table->unsignedBigInteger('views_count')->default(0)->after('type')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('garments')) {
            Schema::table('garments', function (Blueprint $table) {
                if (Schema::hasColumn('garments', 'views_count')) {
                    $table->dropColumn('views_count');
                }
            });
        }

        if (Schema::hasTable('designs')) {
            Schema::table('designs', function (Blueprint $table) {
                if (Schema::hasColumn('designs', 'views_count')) {
                    $table->dropColumn('views_count');
                }
            });
        }
    }
};
