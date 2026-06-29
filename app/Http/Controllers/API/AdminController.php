<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AdminService;
use App\Models\User;
use Throwable;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use App\Enums\RoleEnum;
class AdminController extends Controller
{

    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function dashboard()
    {
        try {
            $stats = $this->adminService->getDashboardStats();
            return $this->successResponse($stats, 'Dashboard fetched');
        } catch (Throwable $e) {
            return $this->errorResponse('Dashboard fetch failed', 500, $e->getMessage());
        }
    }

    // GET /admin/users
    public function allUsers(Request $request)
    {
        try {
            $users = $this->adminService->getUsers(
                $request->only(['role', 'status', 'search'])
            );
            
            return $this->successResponse([
                'data' => UserResource::collection($users->items()),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                ]
            ], 'Users fetched');
        } catch (Throwable $e) {
            return $this->errorResponse('Failed', 500, $e->getMessage());
        }
    }

    // GET /admin/users/{id}
    public function getUser($id)
    {
        try {
            $user = User::with(['userProfile', 'tailorProfile', 'deliveryStaffProfile'])->find($id);

            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse(new UserResource($user), 'User fetched');
        } catch (Throwable $e) {
            return $this->errorResponse('Failed', 500, $e->getMessage());
        }
    }

    // POST /admin/users
    public function createUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:users',
                'phone' => 'required|string|unique:users',
                'password' => 'required|min:6',
                'role' => 'required|in:admin,tailor,customer,delivery_staff',
                // User Profile fields
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'pincode' => 'nullable|string|max:10',
                'gender' => 'nullable|string|in:male,female,other',
                'date_of_birth' => 'nullable|date',
                // Tailor Profile fields
                'shop_name' => 'nullable|string|max:255',
                'tailor_address' => 'nullable|string',
                'experience_years' => 'nullable|integer|min:0',
                'tailor_is_available' => 'nullable|boolean',
                // Delivery Staff Profile fields
                'aadhaar_number' => 'nullable|string|max:20|unique:delivery_staff_profiles,aadhaar_number',
                'vehicle_number' => 'nullable|string|max:50',
                'emergency_contact' => 'nullable|string|max:50',
                'staff_is_available' => 'nullable|boolean',
                'aadhaar_photo' => 'nullable|max:10240',
            ]);

            $path = null;
            if ($request->hasFile('aadhaar_photo')) {
                $request->validate([
                    'aadhaar_photo' => 'image|max:10240',
                ]);
                $path = $request->file('aadhaar_photo')->store('aadhaar_photos', 'public');
            }
            $validated['aadhaar_photo'] = $path;

            $user = $this->adminService->createUser($validated);
            return $this->successResponse(new UserResource($user), 'User created', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (Throwable $e) {
            return $this->errorResponse('User creation failed', 500, $e->getMessage());
        }
    }

    // PUT /admin/users/{id}/status
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,blocked',
            ]);

            $user = User::findOrFail($id);
            $user->status = $request->status;
            $user->save();

            return $this->successResponse(new UserResource($user), 'Status updated');
        } catch (Throwable $e) {
            return $this->errorResponse('Update failed', 500, $e->getMessage());
        }
    }

    // PUT /admin/users/{id}/role
    public function updateRoleStatus(Request $request, $id)
{
    try {

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|unique:users,phone,' . $id,
            'password' => 'nullable|min:6',
            'role' => 'nullable|in:admin,tailor,customer,delivery_staff',
            'status' => 'nullable|in:active,blocked',
            // User Profile fields
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'gender' => 'nullable|string|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            // Tailor Profile fields
            'shop_name' => 'nullable|string|max:255',
            'tailor_address' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'tailor_is_available' => 'nullable|boolean',
            // Delivery Staff Profile fields
            'aadhaar_number' => 'nullable|string|max:20|unique:delivery_staff_profiles,aadhaar_number,' . $id . ',user_id',
            'vehicle_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:50',
            'staff_is_available' => 'nullable|boolean',
            'aadhaar_photo' => 'nullable|max:10240',
        ]);

        if ($request->hasFile('aadhaar_photo')) {
            $request->validate([
                'aadhaar_photo' => 'image|max:10240',
            ]);
            // Delete old photo if exists
            if ($user->deliveryStaffProfile && $user->deliveryStaffProfile->aadhaar_photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->deliveryStaffProfile->aadhaar_photo);
            }
            $validated['aadhaar_photo'] = $request->file('aadhaar_photo')->store('aadhaar_photos', 'public');
        }

        $updatedUser = $this->adminService
            ->updateUserRoleStatus($user, $validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($updatedUser)
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Update failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // DELETE /admin/users/{id}
    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return $this->successResponse(null, 'User deleted');
        } catch (Throwable $e) {
            return $this->errorResponse('Delete failed', 500, $e->getMessage());
        }
    }

    // GET /admin/tailors
    public function allTailors(Request $request)
    {
        try {
            $tailors = User::where('role', RoleEnum::TAILOR)
                ->with('tailorProfile')
                ->latest()
                ->get();
            return $this->successResponse(UserResource::collection($tailors), 'Tailors fetched');
        } catch (Throwable $e) {
            return $this->errorResponse('Failed', 500, $e->getMessage());
        }
    }

    // GET /admin/delivery-staff
 public function allDeliveryStaff(Request $request)
{
    try {

        $staff = User::where(
            'role',
            RoleEnum::DELIVERY_STAFF->value
        )->get();

        return response()->json([
            'success' => true,
            'data' => $staff,
            'message' => 'Staff list fetched'
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);

    }
}

}
