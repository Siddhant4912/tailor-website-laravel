<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
     {
         DB::table('designs')->update([
             'type' => 'garment',
             'additional_price' => 0,
             'secondary_price' => 0
         ]);
     }

     /**
      * Reverse the migrations.
      */
     public function down(): void
     {
         // No rollback needed
     }
};
