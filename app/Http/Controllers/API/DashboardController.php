<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index()
    {
        try {
            $totalOrders     = Order::where('status', '!=', \App\Enums\OrderStatusEnum::Draft)->count();
            $pendingOrders   = Order::where('status', 'pending')->count();
            $completedOrders = Order::where('status', 'delivered')->count();

            // FIX: role values must match the migration enum: ADM, TLR, USR
            // Original code used 'CUSTOMER' and 'TAILOR' — those don't exist in the DB.
            $totalUsers   = User::where('role', \App\Enums\RoleEnum::CUSTOMER)->count();
            $totalTailors = User::where('role', \App\Enums\RoleEnum::TAILOR)->count();

            $totalRevenue = Order::where('status', 'delivered')->sum('total_price');

            $monthlyRevenue = Order::select(
                    DB::raw('YEAR(created_at) as year'),   // FIX: added year so Jan 2025 ≠ Jan 2026
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(total_price) as total')
                )
                ->where('status', 'delivered')
                ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                ->orderBy(DB::raw('YEAR(created_at)'))
                ->orderBy(DB::raw('MONTH(created_at)'))
                ->get();

            // FIX: load user so frontend knows who placed the order
            $recentOrders = Order::with(['items', 'customer'])
                ->where('status', '!=', \App\Enums\OrderStatusEnum::Draft)
                ->latest()
                ->take(5)
                ->get();

            // FIX: added appointments stats — they were completely missing
            $totalAppointments   = Appointment::where('status', '!=', \App\Enums\AppointmentStatusEnum::DRAFT)->count();
            $pendingAppointments = Appointment::where('status', 'pending')->count();

            return $this->successResponse([
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
            ], 'Dashboard data');

        } catch (\Exception $e) {
            return $this->errorResponse('Dashboard failed', 500, $e->getMessage());
        }
    }
}
