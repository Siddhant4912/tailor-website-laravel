<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Exception;

class UserProfileService
{
    /**
     * Fetch or create profile for user, merged with User data
     */
    public function getProfile(int $userId): array
    {
        $user = User::findOrFail($userId);
        $profile = UserProfile::where('user_id', $userId)->first();

        if (!$profile) {
            $profile = UserProfile::create([
                'user_id'  => $userId,
                'address'  => '',
                'city'     => '',
                'state'    => '',
                'pincode'  => ''
            ]);
        }

        // Return combined data mapped perfectly for frontend, falling back to users table if profile is empty
        return [
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'address_line' => $profile->address ?: ($user->address_line ?: ''), // DB 'address' mapped to React 'address_line'
            'city'         => $profile->city ?: ($user->city ?: ''),
            'state'        => $profile->state ?: ($user->state ?: ''),
            'pincode'      => $profile->pincode ?: ($user->pincode ?: ''),
            'profile_photo'=> $profile->profile_photo
        ];
    }

    /**
     * Update user and user profile
     */
    public function updateProfile(int $userId, array $data): array
    {
        $user = User::findOrFail($userId);

        // 1. Update User Table (with address fields sync)
        if (isset($data['name']))  $user->name = $data['name'];
        if (isset($data['phone'])) $user->phone = $data['phone'];
        if (array_key_exists('address_line', $data)) $user->address_line = $data['address_line'];
        if (array_key_exists('city', $data))         $user->city = $data['city'];
        if (array_key_exists('state', $data))        $user->state = $data['state'];
        if (array_key_exists('pincode', $data))      $user->pincode = $data['pincode'];
        $user->save();

        // 2. Update User Profile Table
        $profile = UserProfile::where('user_id', $userId)->first();
        
        $profileData = [];
        if (array_key_exists('address_line', $data)) $profileData['address'] = $data['address_line'];
        if (array_key_exists('city', $data))         $profileData['city'] = $data['city'];
        if (array_key_exists('state', $data))        $profileData['state'] = $data['state'];
        if (array_key_exists('pincode', $data))      $profileData['pincode'] = $data['pincode'];

        if (!$profile) {
            $profileData['user_id'] = $userId;
            UserProfile::create($profileData);
        } elseif (!empty($profileData)) {
            $profile->update($profileData);
        }

        // Return updated merged profile
        return $this->getProfile($userId);
    }
}