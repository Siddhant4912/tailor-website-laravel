<?php

namespace App\Services;
use App\Models\TailorProfile;

class TailorProfileService
{
   public function getProfile(int $userId): TailorProfile
    {
        $profile = TailorProfile::where('user_id', $userId)->first();

        if (!$profile) {
            $profile = TailorProfile::create([
                'user_id'=>$userId,
            'shop_name' => '',
             'shop_address' => '',
    'description' => '',
    ]);
        }

        return $profile;
    }

    /**
     * Update or create profile
     */
    public function updateProfile(int $userId, array $data): TailorProfile
    {
        $profile = TailorProfile::where('user_id', $userId)->first();

        if (!$profile) {
            $data['user_id'] = $userId;
            $profile = TailorProfile::create($data);
        } else {
            $profile->update($data);
        }

        return $profile;
    }
}
