<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Appointment;
use App\Models\Order;

class StaffProfileController extends Controller
{
    /**
     * Get the logged-in staff profile details along with aggregate counts.
     */
    public function getProfile(Request $request)
    {
        $staff = auth()->user()->load(['userProfile', 'deliveryStaffProfile']);

        $totalVisits = Appointment::where('assigned_staff_id', $staff->id)
            ->where('status', 'completed')
            ->count();

        $totalDeliveries = Order::where('delivery_staff_id', $staff->id)
            ->where('status', 'delivered')
            ->count();

        $userId = $staff->id;
        $staffData = array_merge(
            $staff->toArray(),
            $staff->userProfile ? $staff->userProfile->toArray() : [],
            $staff->deliveryStaffProfile ? $staff->deliveryStaffProfile->toArray() : []
        );
        $staffData['id'] = $userId;

        return response()->json([
            'status' => 'success',
            'data' => [
                'staff' => $staffData,
                'stats' => [
                    'total_visits' => $totalVisits,
                    'total_deliveries' => $totalDeliveries,
                ],
            ]
        ]);
    }

    /**
     * Update the staff profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|max:2048',
        ]);

        $staff = auth()->user();
        $userProfile = $staff->userProfile()->firstOrCreate([]);

        if ($request->hasFile('profile_photo')) {
            if ($userProfile->profile_photo) {
                Storage::disk('public')->delete($userProfile->profile_photo);
            }
            $path = $request->file('profile_photo')->store('staff_photos', 'public');
            $userProfile->profile_photo = $path;
            $userProfile->save();
        }

        $userId = $staff->id;
        $staffData = array_merge(
            $staff->toArray(),
            $staff->userProfile ? $staff->userProfile->toArray() : [],
            $staff->deliveryStaffProfile ? $staff->deliveryStaffProfile->toArray() : []
        );
        $staffData['id'] = $userId;

        return response()->json([
            'status' => 'success',
            'message' => 'Profile photo updated successfully',
            'data' => $staffData
        ]);
    }
}
