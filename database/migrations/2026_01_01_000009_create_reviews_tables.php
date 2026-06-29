<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // A review must always belong to an order to verify completion
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            
            $table->foreignId('garment_id')->nullable()->constrained('garments')->cascadeOnDelete();
            
            $table->string('type')->index(); // 'service' or 'garment'
            $table->tinyInteger('rating')->unsigned(); // 1 to 5
            
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            
            $table->string('status')->default('approved')->index(); // Auto-approve by default, pending/rejected if moderated
            
            $table->timestamps();
            $table->softDeletes();
            
            // A user can only submit 1 service review per order
            // Or 1 garment review per garment inside that specific order
            // Utilizing unique constraint on the combination. If garment_id is NULL, it uniquely identifies the service review for that order.
            $table->unique(['user_id', 'order_id', 'type', 'garment_id']);
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Reporter
            $table->string('reason');
            $table->string('status')->default('open')->index();
            $table->timestamps();
            
            // Prevent a user from spam-reporting the same review
            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('garment_rating_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_id')->unique()->constrained('garments')->cascadeOnDelete();
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->integer('rating_1_count')->default(0);
            $table->integer('rating_2_count')->default(0);
            $table->integer('rating_3_count')->default(0);
            $table->integer('rating_4_count')->default(0);
            $table->integer('rating_5_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_rating_stats');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
    }
};
