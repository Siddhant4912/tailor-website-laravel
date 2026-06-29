<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TailorProfileService;
use App\Http\Requests\User\UpdateTailorProfileRequest;

class TailorProfileController extends Controller
{

    protected $service;

    public function __construct(TailorProfileService $service)
    {
        $this->service = $service;
    }

    // GET /tailor/profile
    public function profile(Request $request)
    {
        try {
            $profile = $this->service->getProfile($request->user()->id);
            return $this->successResponse($profile, 'Profile fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch profile', 500, $e->getMessage());
        }
    }

    // PUT /tailor/profile
    public function update(UpdateTailorProfileRequest $request)
    {
        try {
            $profile = $this->service->updateProfile(
                $request->user()->id,
                $request->validated()
            );
            return $this->successResponse($profile, 'Profile updated');
        } catch (\Exception $e) {
            // FIX: original passed $e->getMessage() as the HTTP status code (2nd arg)
            // Correct signature: errorResponse(message, statusCode, debug)
            return $this->errorResponse('Failed to update profile', 500, $e->getMessage());
        }
    }
}
