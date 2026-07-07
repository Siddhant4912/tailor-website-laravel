<?php
// siddhant pawar : 04-07-2026

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

    public function sendOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string',
        ]);

        $type = $request->type;
        $value = $request->value;
        $userId = $request->user()->id;

        // Check if value is already taken by another account
        if ($type === 'email') {
            $exists = \App\Models\User::where('email', $value)->where('id', '!=', $userId)->exists();
            if ($exists) {
                return $this->errorResponse('This email is already registered.', 400);
            }
        } else {
            $exists = \App\Models\User::where('phone', $value)->where('id', '!=', $userId)->exists();
            if ($exists) {
                return $this->errorResponse('This phone number is already registered.', 400);
            }
        }

        // Generate OTP code
        $otp = (string) rand(100000, 999999);

        // Store OTP in database
        \App\Models\Otp::updateOrCreate(
            ['email' => $value],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]
        );

        // Send OTP
        try {
            if ($type === 'email') {
                \Illuminate\Support\Facades\Notification::route('mail', $value)
                    ->notify(new \App\Notifications\SendOtpNotification($otp));
            } else {
                $smsService = app(\App\Services\SmsService::class);
                $smsService->sendOtp($value, $otp);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send OTP verification for profile update: " . $e->getMessage());
            return $this->errorResponse('Failed to send verification code. Please try again.', 500, $e->getMessage());
        }

        return $this->successResponse(null, 'Verification code has been sent successfully.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $type = $request->type;
        $value = $request->value;
        $otp = $request->otp;
        $user = $request->user();

        // Verify OTP
        $otpRecord = \App\Models\Otp::where('email', $value)
            ->where('otp', $otp)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->errorResponse('Invalid or expired verification code.', 400);
        }

        // Consume OTP
        $otpRecord->delete();

        // Update the field
        try {
            if ($type === 'email') {
                $user->email = $value;
                $user->email_verified_at = now();
            } else {
                $user->phone = $value;
                $user->phone_verified_at = now();
            }
            $user->save();

            // Refresh profile from service
            $profile = $this->service->getProfile($user->id);
            return $this->successResponse($profile, ucfirst($type) . ' updated successfully!');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update field.', 500, $e->getMessage());
        }
    }
}
