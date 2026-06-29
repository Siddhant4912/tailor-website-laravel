<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('unit')->default('inch');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed with 8 standard tailor fields
        $fields = [
            ['name' => 'Chest', 'display_name' => 'Chest', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Waist', 'display_name' => 'Waist', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Hip', 'display_name' => 'Hip', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Shoulder', 'display_name' => 'Shoulder', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Length', 'display_name' => 'Length', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Sleeve', 'display_name' => 'Sleeve', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Neck', 'display_name' => 'Neck', 'unit' => 'inch', 'is_active' => true],
            ['name' => 'Inseam', 'display_name' => 'Inseam', 'unit' => 'inch', 'is_active' => true],
        ];

        foreach ($fields as $field) {
            DB::table('measurement_fields')->insert(array_merge($field, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_fields');
    }
};
