<?php
// siddhant pawar : 04-07-2026

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
            ]);
        }

        $formattedAddr = $profile->formatted_address ?: ($user->formatted_address ?: ($profile->address ?: ($user->address_line ?: '')));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => !is_null($user->email_verified_at),
            'phone_verified' => !is_null($user->phone_verified_at),
            'building_name' => $profile->building_name ?? $user->building_name ?? '',
            'flat_number'   => $profile->flat_number ?? $user->flat_number ?? '',
            'wing'          => $profile->wing ?? $user->wing ?? '',
            'street'        => $profile->street ?? $user->street ?? '',
            'locality'      => $profile->locality ?? $user->locality ?? '',
            'landmark'      => $profile->landmark ?? $user->landmark ?? '',
            'city'          => $profile->city ?? $user->city ?? '',
            'district'      => $profile->district ?? $user->district ?? '',
            'state'         => $profile->state ?? $user->state ?? '',
            'pincode'       => $profile->pincode ?? $user->pincode ?? '',
            'address_line'  => $formattedAddr,
            'address'       => $formattedAddr,
            'formatted_address' => $formattedAddr,
            'profile_photo' => $profile->profile_photo ?? null,
        ];
    }

    /**
     * Update user and user profile
     */
    public function updateProfile(int $userId, array $data): array
    {
        $user = User::findOrFail($userId);

        // 1. Update User Table
        if (isset($data['name']))  $user->name = $data['name'];
        if (isset($data['phone'])) $user->phone = $data['phone'];
        if (isset($data['email'])) {
            if ($data['email'] !== $user->email) {
                $exists = User::where('email', $data['email'])->exists();
                if ($exists) {
                    throw new \InvalidArgumentException('The email has already been taken.');
                }
                $user->email = $data['email'];
            }
        }
        foreach (['building_name', 'flat_number', 'wing', 'street', 'locality', 'landmark', 'city', 'district', 'state', 'pincode'] as $f) {
            if (array_key_exists($f, $data)) {
                $user->$f = $data[$f];
            }
        }
        if (array_key_exists('address_line', $data)) $user->address_line = $data['address_line'];
        $user->save();

        // 2. Update User Profile Table
        $profile = UserProfile::where('user_id', $userId)->first();
        
        $profileData = [];
        foreach (['building_name', 'flat_number', 'wing', 'street', 'locality', 'landmark', 'city', 'district', 'state', 'pincode'] as $f) {
            if (array_key_exists($f, $data)) {
                $profileData[$f] = $data[$f];
            }
        }
        if (array_key_exists('address_line', $data)) {
            $profileData['address'] = $data['address_line'];
        }

        if (!$profile) {
            $profileData['user_id'] = $userId;
            $profile = UserProfile::create($profileData);
        } elseif (!empty($profileData)) {
            $profile->update($profileData);
        }

        // Keep address / address_line synced to formatted string if individual components updated
        $newFormatted = $profile->formatted_address;
        if (!empty($newFormatted)) {
            $profile->update(['address' => $newFormatted]);
            $user->update(['address_line' => $newFormatted]);
        }

        return $this->getProfile($userId);
    }
}