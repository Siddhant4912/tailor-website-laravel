<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeDetails = !request()->is('api/me') && !request()->is('api/login') && !request()->is('api/verify-otp');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'profile' => $this->whenLoaded('userProfile', fn() => new UserProfileResource($this->userProfile)),
            'tailor_profile' => $this->whenLoaded('tailorProfile', fn() => new TailorProfileResource($this->tailorProfile)),
            'delivery_profile' => $this->whenLoaded('deliveryStaffProfile', fn() => new DeliveryStaffProfileResource($this->deliveryStaffProfile)),
            'orders_count' => \App\Models\Order::where('customer_id', $this->id)->count(),
            'appointments_count' => \App\Models\Appointment::where('customer_id', $this->id)->count(),
            'tailor_metrics' => $this->when($this->role === \App\Enums\RoleEnum::TAILOR, function() {
                return [
                    'total_assigned' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->count(),
                    'completed' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'completed')->count(),
                    'stitching' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'stitching')->count(),
                    'pending' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'pending')->count(),
                ];
            }),
            'assigned_items' => $this->when($this->role === \App\Enums\RoleEnum::TAILOR, function() {
                $items = \App\Models\OrderItem::with(['order.customer', 'garment'])
                    ->where('assigned_tailor_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return OrderItemResource::collection($items);
            }),
            'orders' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::CUSTOMER || \App\Models\Order::where('customer_id', $this->id)->exists()), function() {
                $orders = \App\Models\Order::where('customer_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $orders->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => is_object($order->status) && isset($order->status->value) ? $order->status->value : (is_object($order->status) ? $order->status->name : $order->status),
                        'total_price' => $order->total_price,
                        'created_at' => $order->created_at?->toDateTimeString(),
                    ];
                });
            }),
            'appointments' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::CUSTOMER || \App\Models\Appointment::where('customer_id', $this->id)->exists()), function() {
                $appointments = \App\Models\Appointment::where('customer_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $appointments->map(function($app) {
                    return [
                        'id' => $app->id,
                        'appointment_date' => $app->appointment_date instanceof \DateTimeInterface ? $app->appointment_date->toDateString() : $app->appointment_date,
                        'appointment_time' => $app->appointment_time,
                        'status' => is_object($app->status) && isset($app->status->value) ? $app->status->value : (is_object($app->status) ? $app->status->name : $app->status),
                        'created_at' => $app->created_at?->toDateTimeString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
