<?php

namespace Database\Seeders;

use App\Models\GstSetting;
use Illuminate\Database\Seeder;

class GstSettingSeeder extends Seeder
{
    public function run(): void
    {
        GstSetting::create([
            'rate' => 18.00,
            'is_active' => true,
        ]);
    }
}
