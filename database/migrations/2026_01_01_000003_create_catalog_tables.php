<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloth_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('garments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('cloth_categories')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs')->cascadeOnDelete();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('image')->nullable(); // Should probably rename to images or handle single
            $table->decimal('price', 10, 2);
            $table->integer('stitching_time_days')->default(3); // better naming
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Indexes for optimizing queries
            $table->index(['category_id', 'design_id']);
        });

        Schema::create('garment_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_id')->constrained('garments')->cascadeOnDelete();
            $table->string('field_name'); // e.g., 'Chest', 'Length'
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            
            $table->unique(['garment_id', 'field_name']); // Prevent duplicate measurement fields per garment
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_measurements');
        Schema::dropIfExists('garments');
        Schema::dropIfExists('designs');
        Schema::dropIfExists('cloth_categories');
    }
};
