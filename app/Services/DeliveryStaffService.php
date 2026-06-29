<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeliveryStaffService
{
    public function create(array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        return User::create($data);
    }

    public function getAll()
    {
        // Fixed: Use User model with delivery_staff role
        return User::where('role', 'delivery_staff')->latest()->get();
    }

    public function getById($id)
    {
        return User::where('role', 'delivery_staff')->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $staff = User::where('role', 'delivery_staff')->findOrFail($id);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $staff->update($data);
        return $staff;
    }

    public function delete($id)
    {
        return User::where('role', 'delivery_staff')->findOrFail($id)->delete();
    }

    public function login($phone, $password)
    {
        // Add role check to ensure only delivery staff can login here
        $staff = User::where('phone', $phone)->where('role', 'delivery_staff')->first();

        if (!$staff || !Hash::check($password, $staff->password)) {
            throw new \Exception('Invalid credentials');
        }

        if ($staff->status !== 'active') {
            throw new \Exception('Account is inactive');
        }

        // delete old tokens
        $staff->tokens()->delete();

        $token = $staff->createToken('delivery-staff-token')->plainTextToken;

        return [
            'staff' => $staff,
            'token' => $token,
        ];
    }

    // Fixed: Changed 'DeliveryStaff $staff' to 'User $staff' to prevent Fatal Type Error
    public function logout(User $staff)
    {
        $staff->currentAccessToken()->delete();
    }

    public function getAll_available()
    {
        return User::where('role', 'delivery_staff')
            ->where('is_available', true)
            ->where('status', 'active')
            ->get();
    }

    public function updateLocation(User $staff, array $data)
    {
        $staff->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
        return $staff;
    }
}