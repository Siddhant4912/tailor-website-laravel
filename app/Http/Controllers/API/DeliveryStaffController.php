<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DeliveryStaffService;
use App\Models\Order;
use App\Models\User;
use App\Http\Resources\OrderResource;

class DeliveryStaffController extends Controller
{

    public function __construct(protected DeliveryStaffService $service)
    {
    }

    // POST /delivery-staff
    public function store(Request $request)
    {
        try {

            $data = $request->validate([
                'name' => 'required|string',
                'phone' => 'required|unique:users,phone',
                'email' => 'nullable|email|unique:users,email',
                'aadhaar_number' => 'required|unique:users,aadhaar_number',
                'gender' => 'nullable|in:male,female,other',
                'address' => 'nullable|string',
                'password' => 'required|min:6',
            ]);

            // Role and Status set exactly as needed for login and middleware
            $data['role'] = 'delivery_staff';
            $data['status'] = 'active';

            // Service handles the password hashing
            $staff = $this->service->create($data);

            return $this->successResponse(
                $staff,
                'Delivery staff created',
                201
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // POST /delivery-staff/login
    public function login(Request $request)
    {
        try {

            $data = $request->validate([
                'phone' => 'required',
                'password' => 'required',
            ]);

            $res = $this->service->login(
                $data['phone'],
                $data['password']
            );

            return $this->successResponse(
                $res,
                'Login successful'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                401
            );

        }
    }

    // POST /delivery-staff/logout
    public function logout(Request $request)
    {
        try {

            $this->service->logout($request->user());

            return $this->successResponse(
                null,
                'Logged out'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // GET /delivery-staff
    public function index()
    {
        try {

            $staff = User::where('role', 'delivery_staff')
                ->latest()
                ->get();

            return $this->successResponse(
                $staff,
                'Staff list fetched'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // GET /delivery-staff/{id}
    public function show($id)
    {
        try {

            $staff = User::where('role', 'delivery_staff')
                ->findOrFail($id);

            return $this->successResponse(
                $staff,
                'Staff fetched'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                'Staff not found',
                404
            );

        }
    }

    // PUT /delivery-staff/{id}
    public function update(Request $request, $id)
    {
        try {

            $staff = User::where('role', 'delivery_staff')
                ->findOrFail($id);

            $data = $request->validate([
                'name' => 'nullable|string',
                'phone' => 'nullable|unique:users,phone,' . $id,
                'email' => 'nullable|email|unique:users,email,' . $id,
                'aadhaar_number' => 'nullable|unique:users,aadhaar_number,' . $id,
                'gender' => 'nullable|in:male,female,other',
                'address' => 'nullable|string',
                'password' => 'nullable|min:6',
                'status' => 'nullable|in:active,inactive'
            ]);

            // Update in controller direct since it bypasses the service in your old code
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }

            $staff->update($data);

            return $this->successResponse(
                $staff,
                'Updated successfully'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                'Failed to update',
                500,
                $e->getMessage()
            );

        }
    }

    // DELETE /delivery-staff/{id}
    public function destroy($id)
    {
        try {

            $staff = User::where('role', 'delivery_staff')
                ->findOrFail($id);

            $staff->delete();

            return $this->successResponse(
                null,
                'Deleted'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                'Failed to delete',
                500,
                $e->getMessage()
            );

        }
    }

    // PUT /delivery-staff/location
    public function updateLocation(Request $request)
    {
        try {

            $data = $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $user = $request->user();

            $user->update([
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ]);

            return $this->successResponse(
                $user,
                'Location updated'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // GET /delivery-staff/available
    public function available()
    {
        try {

            $staff = User::where('role', 'delivery_staff')
                ->get();

            return $this->successResponse(
                $staff,
                'Available staff'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // GET /delivery-staff/my-orders
    public function myOrders(Request $request)
    {
        try {

            $staffId = $request->user()->id;

            $orders = Order::with([
                'customer',
                'items.garment',
                'measurements',
                'invoices.transactions',
                'statusLogs'
            ])
                ->where(function ($q) use ($staffId) {
                    $q->where('delivery_staff_id', $staffId)
                        ->orWhere('pickup_staff_id', $staffId);
                })
                ->latest()
                ->get();

            return $this->successResponse(
                OrderResource::collection($orders),
                'My orders fetched'
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                $e->getMessage(),
                500
            );

        }
    }

    // POST /staff/orders/{id}/uploads
    public function uploadOrderPhoto(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:5120']);

        try {
            $staffId = $request->user()->id;
            $order = Order::where('id', $id)
                ->where(function ($q) use ($staffId) {
                    $q->where('delivery_staff_id', $staffId)
                        ->orWhere('pickup_staff_id', $staffId);
                })
                ->firstOrFail();

            $path = $request->file('image')
                ->store('orders/' . $order->id, 'public');

            $order->update(['delivery_proof' => $path]);

            return $this->successResponse(new OrderResource($order->fresh()), 'Delivery proof photo uploaded successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}