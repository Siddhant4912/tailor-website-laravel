<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\TailorProfile;
use App\Models\DeliveryStaffProfile;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@tailor.com',
            'phone' => '9999999999',
            'password' => Hash::make('admin123'),
            'role' => RoleEnum::ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 2. Tailor User
        $tailor = User::create([
            'name' => 'Master Tailor',
            'email' => 'tailor@tailor.com',
            'phone' => '8888888888',
            'password' => Hash::make('tailor123'),
            'role' => RoleEnum::TAILOR,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        TailorProfile::create([
            'user_id' => $tailor->id,
            'shop_name' => 'Master Stitchers',
            'address' => '123 Tailor Street, NY',
            'rating' => 4.8,
            'experience_years' => 10,
        ]);

        // 3. Delivery Staff User
        $delivery = User::create([
            'name' => 'Speedy Delivery',
            'email' => 'delivery@tailor.com',
            'phone' => '7777777777',
            'password' => Hash::make('delivery123'),
            'role' => RoleEnum::DELIVERY_STAFF,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        DeliveryStaffProfile::create([
            'user_id' => $delivery->id,
            'aadhaar_number' => '123456789012',
            'vehicle_number' => 'NY-01-AB-1234',
        ]);

        // 4. Customer User
        $customer = User::create([
            'name' => 'John Customer',
            'email' => 'customer@tailor.com',
            'phone' => '6666666666',
            'password' => Hash::make('customer123'),
            'role' => RoleEnum::CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $customer->id,
            'address' => '456 Customer Ave, NY',
            'city' => 'New York',
            'state' => 'NY',
            'pincode' => '10001',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
        ]);
        
        // Generate a few random customers
        for ($i = 1; $i <= 3; $i++) {
            $user = User::create([
                'name' => "Random Customer $i",
                'email' => "random$i@tailor.com",
                'phone' => "555000000$i",
                'password' => Hash::make('password'),
                'role' => RoleEnum::CUSTOMER,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
            UserProfile::create([
                'user_id' => $user->id,
                'address' => "Random Street $i",
                'city' => 'New York',
            ]);
        }
    }
}
