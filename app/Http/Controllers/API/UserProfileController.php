<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\UserProfileService;
use App\Http\Requests\User\UpdateUserProfileRequest;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{

    protected $service;

    public function __construct(UserProfileService $service)
    {
        $this->service = $service;
    }

    // GET /user/profile
    public function profile(Request $request)
    {
        try {
            $profile = $this->service->getProfile($request->user()->id);
            return $this->successResponse($profile, 'Profile fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch profile', 500, $e->getMessage());
        }
    }

    // PUT /user/profile
    public function update(UpdateUserProfileRequest $request)
    {
        try {
            $profile = $this->service->updateProfile(
                $request->user()->id,
                $request->validated()
            );
            return $this->successResponse($profile, 'Profile updated');
        } catch (\Exception $e) {
            // FIX: same bug as TailorProfileController — $e->getMessage() was passed
            // as the HTTP status code (2nd argument). Correct order: (message, code, debug)
            return $this->errorResponse('Failed to update profile', 500, $e->getMessage());
        }
    }

    // POST /user/profile/photo
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'profile_photo' => 'required|image|max:2048',
        ]);

        try {
            $user = $request->user();
            $userProfile = $user->userProfile()->firstOrCreate([]);

            if ($request->hasFile('profile_photo')) {
                if ($userProfile->profile_photo) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($userProfile->profile_photo);
                }
                $path = $request->file('profile_photo')->store('user_photos', 'public');
                $userProfile->profile_photo = $path;
                $userProfile->save();
            }

            // Return updated profile data
            $profile = $this->service->getProfile($user->id);
            return $this->successResponse($profile, 'Profile photo updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update profile photo', 500, $e->getMessage());
        }
    }
}
