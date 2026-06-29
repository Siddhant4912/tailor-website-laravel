<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminService
{
    /**
     * Create a new user
     */
    public function createUser(array $data)
    {
        DB::beginTransaction();
        try {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ]);
            $user->role = $data['role'];
            $user->save();

            // Create User Profile
            $user->userProfile()->create([
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
            ]);

            // Create Tailor Profile
            if ($user->role === \App\Enums\RoleEnum::TAILOR || $data['role'] === 'tailor') {
                $user->tailorProfile()->create([
                    'shop_name' => $data['shop_name'] ?? null,
                    'address' => $data['tailor_address'] ?? null,
                    'experience_years' => $data['experience_years'] ?? 0,
                    'is_available' => filter_var($data['tailor_is_available'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }

            // Create Delivery Staff Profile
            if ($user->role === \App\Enums\RoleEnum::DELIVERY_STAFF || $data['role'] === 'delivery_staff') {
                $user->deliveryStaffProfile()->create([
                    'aadhaar_number' => $data['aadhaar_number'] ?? '',
                    'vehicle_number' => $data['vehicle_number'] ?? null,
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                    'aadhaar_photo' => $data['aadhaar_photo'] ?? null,
                    'is_available' => filter_var($data['staff_is_available'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }

            DB::commit();
            return $user;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user role and status
     */
    public function updateUserRoleStatus(User $user, array $data)
    {
        DB::beginTransaction();

        try {
            // NAME
            $user->name = $data['name'] ?? $user->name;

            // EMAIL
            $user->email = $data['email'] ?? $user->email;

            // PHONE
            $user->phone = $data['phone'] ?? $user->phone;

            // ROLE
            $user->role = $data['role'] ?? $user->role;

            // STATUS
            $user->status = $data['status'] ?? $user->status;

            // PASSWORD
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            // Update User Profile
            $user->userProfile()->updateOrCreate([], [
                'address' => $data['address'] ?? ($user->userProfile?->address),
                'city' => $data['city'] ?? ($user->userProfile?->city),
                'state' => $data['state'] ?? ($user->userProfile?->state),
                'pincode' => $data['pincode'] ?? ($user->userProfile?->pincode),
                'gender' => $data['gender'] ?? ($user->userProfile?->gender),
                'date_of_birth' => $data['date_of_birth'] ?? ($user->userProfile?->date_of_birth),
            ]);

            // Update Tailor Profile
            if ($user->role === \App\Enums\RoleEnum::TAILOR) {
                $user->tailorProfile()->updateOrCreate([], [
                    'shop_name' => $data['shop_name'] ?? ($user->tailorProfile?->shop_name),
                    'address' => $data['tailor_address'] ?? ($user->tailorProfile?->address),
                    'experience_years' => isset($data['experience_years']) ? intval($data['experience_years']) : ($user->tailorProfile?->experience_years ?? 0),
                    'is_available' => isset($data['tailor_is_available']) ? filter_var($data['tailor_is_available'], FILTER_VALIDATE_BOOLEAN) : ($user->tailorProfile?->is_available ?? true),
                ]);
            }

            // Update Delivery Staff Profile
            if ($user->role === \App\Enums\RoleEnum::DELIVERY_STAFF) {
                $user->deliveryStaffProfile()->updateOrCreate([], [
                    'aadhaar_number' => $data['aadhaar_number'] ?? ($user->deliveryStaffProfile?->aadhaar_number ?? ''),
                    'vehicle_number' => $data['vehicle_number'] ?? ($user->deliveryStaffProfile?->vehicle_number),
                    'emergency_contact' => $data['emergency_contact'] ?? ($user->deliveryStaffProfile?->emergency_contact),
                    'aadhaar_photo' => $data['aadhaar_photo'] ?? ($user->deliveryStaffProfile?->aadhaar_photo),
                    'is_available' => isset($data['staff_is_available']) ? filter_var($data['staff_is_available'], FILTER_VALIDATE_BOOLEAN) : ($user->deliveryStaffProfile?->is_available ?? true),
                ]);
            }

            DB::commit();
            return $user;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all users with filters
     */
    public function getUsers(array $filters = [])
    {
        $query = User::with(['userProfile', 'tailorProfile', 'deliveryStaffProfile']);

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('id', 'desc')->paginate(10);
    }

    public function getDashboardStats()
    {
        $totalOrders     = \App\Models\Order::count();
        $pendingOrders   = \App\Models\Order::where('status', 'pending')->count();
        $completedOrders = \App\Models\Order::where('status', 'delivered')->count();

        $totalUsers   = User::where('role', \App\Enums\RoleEnum::CUSTOMER)->count();
        $totalTailors = User::where('role', \App\Enums\RoleEnum::TAILOR)->count();

        $totalRevenue = \App\Models\Order::where('status', 'delivered')->sum('total_price');

        $monthlyRevenue = \App\Models\Order::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_price) as total')
            )
            ->where('status', 'delivered')
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('YEAR(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get();

        $recentOrders = \App\Models\Order::with(['items', 'customer'])
            ->latest()
            ->take(5)
            ->get();

        $totalAppointments   = \App\Models\Appointment::count();
        $pendingAppointments = \App\Models\Appointment::where('status', 'pending')->count();

        return [
            'counts' => [
                'total_orders'          => $totalOrders,
                'pending_orders'        => $pendingOrders,
                'completed_orders'      => $completedOrders,
                'total_users'           => $totalUsers,
                'total_tailors'         => $totalTailors,
                'total_appointments'    => $totalAppointments,
                'pending_appointments'  => $pendingAppointments,
            ],
            'revenue' => [
                'total_revenue' => $totalRevenue,
                'monthly'       => $monthlyRevenue,
            ],
            'recent_orders' => \App\Http\Resources\OrderResource::collection($recentOrders),
        ];
    }
}