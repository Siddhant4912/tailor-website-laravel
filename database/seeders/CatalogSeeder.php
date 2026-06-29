<?php

namespace Database\Seeders;

use App\Models\ClothCategory;
use App\Models\Design;
use App\Models\Garment;
use App\Models\GarmentMeasurement;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $mensWear = ClothCategory::create(['name' => "Men's Wear", 'description' => 'Tailoring for men', 'gender' => 'male']);
        $womensWear = ClothCategory::create(['name' => "Women's Wear", 'description' => 'Tailoring for women', 'gender' => 'female']);

        // Designs
        $suitDesign = Design::create(['category_id' => $mensWear->id, 'name' => 'Classic 2-Piece Suit', 'description' => 'Formal suit']);
        $shirtDesign = Design::create(['category_id' => $mensWear->id, 'name' => 'Formal Shirt', 'description' => 'Cotton formal shirt']);
        $blouseDesign = Design::create(['category_id' => $womensWear->id, 'name' => 'Designer Blouse', 'description' => 'Intricate embroidery blouse']);

        // Garments
        $suit = Garment::create([
            'category_id' => $mensWear->id,
            'design_id' => $suitDesign->id,
            'name' => 'Premium Wool Suit',
            'price' => 5000.00,
            'stitching_time_days' => 7,
        ]);

        $shirt = Garment::create([
            'category_id' => $mensWear->id,
            'design_id' => $shirtDesign->id,
            'name' => 'Cotton Formal Shirt',
            'price' => 800.00,
            'stitching_time_days' => 3,
        ]);

        $blouse = Garment::create([
            'category_id' => $womensWear->id,
            'design_id' => $blouseDesign->id,
            'name' => 'Silk Embroidered Blouse',
            'price' => 1500.00,
            'stitching_time_days' => 5,
        ]);

        // Measurements
        $suitMeasurements = ['Chest', 'Waist', 'Sleeve Length', 'Shoulder', 'Pant Length'];
        foreach ($suitMeasurements as $m) {
            GarmentMeasurement::create(['garment_id' => $suit->id, 'field_name' => $m, 'is_required' => true]);
        }

        $shirtMeasurements = ['Chest', 'Shoulder', 'Neck', 'Sleeve'];
        foreach ($shirtMeasurements as $m) {
            GarmentMeasurement::create(['garment_id' => $shirt->id, 'field_name' => $m, 'is_required' => true]);
        }
    }
}
