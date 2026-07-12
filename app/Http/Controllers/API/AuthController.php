<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Notifications\SendOtpNotification;
use App\Enums\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    // Set this to true to test email OTP instead of phone SMS OTP for registration/login
    private $useEmailOtpForTesting = false;

    public function register(Request $request)
    {
        if ($request->has('email') && $request->email === '') {
            $request->merge(['email' => null]);
        }

        $phoneInput = $request->phone;
        $emailInput = $request->email;
        $excludeId = null;
        $existingUser = null;

        if (!empty($phoneInput)) {
            $existingUser = User::where(function ($query) use ($phoneInput, $emailInput) {
                $query->where('phone', $phoneInput);
                if (!empty($emailInput)) {
                    $query->orWhere('email', $emailInput);
                }
            })->first();

            $isVerified = $existingUser ? ($this->useEmailOtpForTesting ? !is_null($existingUser->email_verified_at) : !is_null($existingUser->phone_verified_at)) : false;
            if ($existingUser && !$isVerified) {
                $excludeId = $existingUser->id;
            }
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email' . ($excludeId ? ',' . $excludeId : ''),
                'phone' => 'required|string|max:20|unique:users,phone' . ($excludeId ? ',' . $excludeId : ''),
                'password' => 'required|string|min:8',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Registration Validation Failed: ' . json_encode($e->errors()));
            throw $e;
        }

        $isVerified = $existingUser ? ($this->useEmailOtpForTesting ? !is_null($existingUser->email_verified_at) : !is_null($existingUser->phone_verified_at)) : false;
        if ($existingUser && !$isVerified) {
            $existingUser->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
            ]);
            $user = $existingUser;
        } else {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->role = 'customer';
            $user->save();

            // Create specific profiles based on role
            if ($user->role === RoleEnum::CUSTOMER) {
                $user->userProfile()->create();
            }
        }

        // Generate and Send OTP
        $otp = (string) rand(100000, 999999);

        if ($this->useEmailOtpForTesting && !empty($user->email)) {
            Otp::create([
                'email' => $user->email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]);

            try {
                $user->notify(new SendOtpNotification($otp));
            } catch (\Exception $e) {
                \Log::error('OTP email failed to send: ' . $e->getMessage());
            }

            return $this->successResponse([
                'verification_required' => true,
                'email' => $user->email,
            ], 'User registered successfully. Verification OTP sent to your email.', 201);
        } else {
            Otp::create([
                'email' => $user->phone,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]);

            try {
                $smsService = app(\App\Services\SmsService::class);
                $smsService->sendOtp($user->phone, $otp);
            } catch (\Exception $e) {
                \Log::error('OTP SMS failed to send during registration: ' . $e->getMessage());
            }

            return $this->successResponse([
                'verification_required' => true,
                'email' => $user->phone,
            ], 'User registered successfully. Verification OTP sent to your phone.', 201);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $loginInput = $request->email;

        $user = User::where(function ($query) use ($loginInput) {
            $query->where('email', $loginInput)
                ->orWhere('phone', $loginInput);

            $cleaned = preg_replace('/[^0-9]/', '', $loginInput);
            if (strlen($cleaned) >= 10) {
                $query->orWhere('phone', 'like', '%' . substr($cleaned, -10));
            }
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->isDeliveryStaff()) {
            throw ValidationException::withMessages([
                'email' => ['This account belongs to delivery staff. Please use the staff login portal.'],
            ]);
        }

        if ($user->isTailor()) {
            throw ValidationException::withMessages([
                'email' => ['This account belongs to tailor. Please use the tailor login portal.'],
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been blocked.'],
            ]);
        }

        // Check if unverified
        $isVerified = $this->useEmailOtpForTesting ? !is_null($user->email_verified_at) : !is_null($user->phone_verified_at);
        if (!$isVerified && $user->role !== RoleEnum::ADMIN) {
            $otp = (string) rand(100000, 999999);

            if ($this->useEmailOtpForTesting && !empty($user->email)) {
                Otp::create([
                    'email' => $user->email,
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(15),
                ]);

                try {
                    $user->notify(new SendOtpNotification($otp));
                } catch (\Exception $e) {
                    \Log::error('OTP login email failed: ' . $e->getMessage());
                }

                return $this->successResponse([
                    'verification_required' => true,
                    'email' => $user->email,
                ], 'Verification required. A new OTP has been sent to your email.');
            } else {
                Otp::create([
                    'email' => $user->phone,
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(15),
                ]);

                try {
                    $smsService = app(\App\Services\SmsService::class);
                    $smsService->sendOtp($user->phone, $otp);
                } catch (\Exception $e) {
                    \Log::error('OTP SMS failed to send during login: ' . $e->getMessage());
                }

                return $this->successResponse([
                    'verification_required' => true,
                    'email' => $user->phone,
                ], 'Verification required. A new OTP has been sent to your phone.');
            }
        }

        // Load profiles based on role
        $user->load(['userProfile', 'tailorProfile', 'deliveryStaffProfile']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ], 'Login successful');
    }

    public function appLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $loginInput = $request->email;

        $user = User::where(function ($query) use ($loginInput) {
            $query->where('email', $loginInput)
                ->orWhere('phone', $loginInput);

            $cleaned = preg_replace('/[^0-9]/', '', $loginInput);
            if (strlen($cleaned) >= 10) {
                $query->orWhere('phone', 'like', '%' . substr($cleaned, -10));
            }
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->isTailor()) {
            throw ValidationException::withMessages([
                'email' => ['This account belongs to tailor. Please use the tailor login portal.'],
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been blocked.'],
            ]);
        }

        // Check if unverified (only for customer roles, bypass for delivery staff and admin)
        if ($user->role === RoleEnum::CUSTOMER) {
            $isVerified = $this->useEmailOtpForTesting ? !is_null($user->email_verified_at) : !is_null($user->phone_verified_at);
            if (!$isVerified) {
                $otp = (string) rand(100000, 999999);

                if ($this->useEmailOtpForTesting && !empty($user->email)) {
                    Otp::where('email', $user->email)->delete();
                    Otp::create([
                        'email' => $user->email,
                        'otp' => $otp,
                        'expires_at' => now()->addMinutes(15),
                    ]);

                    try {
                        $user->notify(new SendOtpNotification($otp));
                    } catch (\Exception $e) {
                        \Log::error('OTP login email failed: ' . $e->getMessage());
                    }

                    return $this->successResponse([
                        'verification_required' => true,
                        'email' => $user->email,
                    ], 'Verification required. A new OTP has been sent to your email.');
                } else {
                    Otp::where('email', $user->phone)->delete();
                    Otp::create([
                        'email' => $user->phone,
                        'otp' => $otp,
                        'expires_at' => now()->addMinutes(15),
                    ]);

                    try {
                        $smsService = app(\App\Services\SmsService::class);
                        $smsService->sendOtp($user->phone, $otp);
                    } catch (\Exception $e) {
                        \Log::error('OTP SMS failed to send during login: ' . $e->getMessage());
                    }

                    return $this->successResponse([
                        'verification_required' => true,
                        'email' => $user->phone,
                    ], 'Verification required. A new OTP has been sent to your phone.');
                }
            }
        }

        // Load profiles based on role
        $user->load(['userProfile', 'tailorProfile', 'deliveryStaffProfile']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ], 'Login successful');
    }

    /**
     * Send OTP / Resend OTP
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
        ]);

        $loginInput = $request->email;
        $user = User::where(function ($query) use ($loginInput) {
            $query->where('email', $loginInput)
                ->orWhere('phone', $loginInput);

            $cleaned = preg_replace('/[^0-9]/', '', $loginInput);
            if (strlen($cleaned) >= 10) {
                $query->orWhere('phone', 'like', '%' . substr($cleaned, -10));
            }
        })->first();

        if (!$user) {
            return $this->errorResponse('No account found with this email or phone number.', 404);
        }

        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);
        $otp = (string) rand(100000, 999999);

        if ($isEmail) {
            // Remove previous OTPs for this email to avoid clutter
            Otp::where('email', $user->email)->delete();

            Otp::create([
                'email' => $user->email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]);

            try {
                $user->notify(new SendOtpNotification($otp));
            } catch (\Exception $e) {
                \Log::error('Resend OTP email failed: ' . $e->getMessage());
                return $this->errorResponse('Failed to send email. Please try again.', 500, $e->getMessage());
            }

            return $this->successResponse([
                'email' => $user->email,
            ], 'A fresh verification OTP has been sent to your email.');
        } else {
            // Remove previous OTPs for this phone to avoid clutter
            Otp::where('email', $user->phone)->delete();

            Otp::create([
                'email' => $user->phone,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]);

            // COMMENT THIS BLOCK TO DISABLE PHONE SMS OTP:
            try {
                $smsService = app(\App\Services\SmsService::class);
                $smsService->sendOtp($user->phone, $otp);
            } catch (\Exception $e) {
                \Log::error('Resend OTP SMS failed: ' . $e->getMessage());
                return $this->errorResponse('Failed to send SMS. Please try again.', 500, $e->getMessage());
            }

            // UNCOMMENT FOR TESTING EMAIL OTP:
            // if (!empty($user->email)) {
            //     try {
            //         $user->notify(new SendOtpNotification($otp));
            //     } catch (\Exception $e) {
            //         \Log::error('Resend OTP email failed: ' . $e->getMessage());
            //     }
            // }

            return $this->successResponse([
                'email' => $user->phone,
            ], 'A fresh verification OTP has been sent to your phone.');
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $identifier = $request->email;
        $otpRecord = Otp::where('email', $identifier)
            ->where('otp', $request->otp)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->errorResponse('Invalid or expired OTP code.', 400);
        }

        // Consume OTP
        $otpRecord->delete();

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (!$user) {
            return $this->errorResponse('No account found with this email or phone number.', 404);
        }

        if ($user->isDeliveryStaff()) {
            return $this->errorResponse('This account belongs to delivery staff. Please use the staff login portal.', 403);
        }

        if ($user->isTailor()) {
            return $this->errorResponse('This account belongs to tailor. Please use the tailor login portal.', 403);
        }

        if ($identifier === $user->email) {
            $user->email_verified_at = now();
        } else {
            $user->phone_verified_at = now();
        }
        $user->save();

        // Auto-create customer profile if missing
        if ($user->role === RoleEnum::CUSTOMER) {
            $user->userProfile()->firstOrCreate([]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load(['userProfile', 'tailorProfile', 'deliveryStaffProfile']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Account verified successfully! You are now logged in.');
    }

    /**
     * Reset Password using OTP
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $identifier = $request->email;
        $otpRecord = Otp::where('email', $identifier)
            ->where('otp', $request->otp)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->errorResponse('Invalid or expired OTP code.', 400);
        }

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (!$user) {
            return $this->errorResponse('No account found with this email or phone number.', 404);
        }

        // Consume OTP
        $otpRecord->delete();

        // Update password
        $user->password = Hash::make($request->password);
        $user->email_verified_at = now(); // Also verify email since they entered the correct OTP
        $user->save();

        return $this->successResponse(null, 'Password has been reset successfully. You can now login with your new password.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['userProfile', 'tailorProfile', 'deliveryStaffProfile']);
        return $this->successResponse(new UserResource($user), 'User fetched');
    }
}
