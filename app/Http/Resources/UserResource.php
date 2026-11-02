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
            'profile' => $this->whenLoaded('userProfile', fn() => $this->userProfile ? new UserProfileResource($this->userProfile) : null),
            'tailor_profile' => $this->whenLoaded('tailorProfile', fn() => $this->tailorProfile ? new TailorProfileResource($this->tailorProfile) : null),
            'delivery_profile' => $this->whenLoaded('deliveryStaffProfile', fn() => $this->deliveryStaffProfile ? new DeliveryStaffProfileResource($this->deliveryStaffProfile) : null),
            'orders_count' => \App\Models\Order::where('customer_id', $this->id)->count(),
            'appointments_count' => \App\Models\Appointment::where('customer_id', $this->id)->count(),
            'tailor_metrics' => $this->when($this->role === \App\Enums\RoleEnum::TAILOR || (is_object($this->role) ? $this->role->value : $this->role) === 'tailor', function() {
                return [
                    'total_assigned' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->count(),
                    'completed' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'completed')->count(),
                    'stitching' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'stitching')->count(),
                    'pending' => \App\Models\OrderItem::where('assigned_tailor_id', $this->id)->where('status', 'pending')->count(),
                ];
            }),
            'assigned_items' => $this->when($this->role === \App\Enums\RoleEnum::TAILOR || (is_object($this->role) ? $this->role->value : $this->role) === 'tailor', function() {
                $items = \App\Models\OrderItem::with(['order.customer', 'garment'])
                    ->where('assigned_tailor_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return OrderItemResource::collection($items);
            }),
            'staff_metrics' => $this->when($this->role === \App\Enums\RoleEnum::DELIVERY_STAFF || (is_object($this->role) ? $this->role->value : $this->role) === 'delivery_staff', function() {
                return [
                    'total_deliveries' => \App\Models\Order::where(function($q) {
                        $q->where('delivery_staff_id', $this->id)->orWhere('pickup_staff_id', $this->id);
                    })->count(),
                    'delivered' => \App\Models\Order::where('delivery_staff_id', $this->id)->where('status', 'delivered')->count(),
                    'active_orders' => \App\Models\Order::where(function($q) {
                        $q->where('delivery_staff_id', $this->id)->orWhere('pickup_staff_id', $this->id);
                    })->whereNotIn('status', ['delivered', 'cancelled'])->count(),
                    'appointments_count' => \App\Models\Appointment::where('assigned_staff_id', $this->id)->count(),
                ];
            }),
            'staff_orders' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::DELIVERY_STAFF || (is_object($this->role) ? $this->role->value : $this->role) === 'delivery_staff'), function() {
                $orders = \App\Models\Order::with('customer')
                    ->where(function($q) {
                        $q->where('delivery_staff_id', $this->id)->orWhere('pickup_staff_id', $this->id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $orders->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer?->name ?? 'N/A',
                        'customer_phone' => $order->customer?->phone ?? 'N/A',
                        'delivery_address' => $order->formatted_address,
                        'status' => is_object($order->status) && isset($order->status->value) ? $order->status->value : (is_object($order->status) ? $order->status->name : $order->status),
                        'total_price' => $order->total_price,
                        'delivery_date' => $order->delivery_date instanceof \DateTimeInterface ? $order->delivery_date->toDateString() : $order->delivery_date,
                        'is_pickup' => $order->pickup_staff_id == $this->id,
                        'is_delivery' => $order->delivery_staff_id == $this->id,
                        'created_at' => $order->created_at?->toDateTimeString(),
                    ];
                });
            }),
            'staff_appointments' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::DELIVERY_STAFF || (is_object($this->role) ? $this->role->value : $this->role) === 'delivery_staff'), function() {
                $appointments = \App\Models\Appointment::with('customer')
                    ->where('assigned_staff_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $appointments->map(function($app) {
                    return [
                        'id' => $app->id,
                        'customer_name' => $app->customer?->name ?? 'N/A',
                        'customer_phone' => $app->customer?->phone ?? 'N/A',
                        'appointment_date' => $app->appointment_date instanceof \DateTimeInterface ? $app->appointment_date->toDateString() : $app->appointment_date,
                        'appointment_time' => $app->appointment_time,
                        'status' => is_object($app->status) && isset($app->status->value) ? $app->status->value : (is_object($app->status) ? $app->status->name : $app->status),
                        'created_at' => $app->created_at?->toDateTimeString(),
                    ];
                });
            }),
            'orders' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::CUSTOMER || (is_object($this->role) ? $this->role->value : $this->role) === 'customer' || \App\Models\Order::where('customer_id', $this->id)->exists()), function() {
                $orders = \App\Models\Order::with(['items.garment', 'deliveryStaff'])
                    ->where('customer_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $orders->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => is_object($order->status) && isset($order->status->value) ? $order->status->value : (is_object($order->status) ? $order->status->name : $order->status),
                        'total_price' => $order->total_price,
                        'subtotal' => $order->subtotal,
                        'advance_paid' => $order->advance_paid,
                        'delivery_date' => $order->delivery_date instanceof \DateTimeInterface ? $order->delivery_date->toDateString() : $order->delivery_date,
                        'delivery_address' => $order->formatted_address,
                        'delivery_staff' => $order->deliveryStaff ? [
                            'id' => $order->deliveryStaff->id,
                            'name' => $order->deliveryStaff->name,
                            'phone' => $order->deliveryStaff->phone,
                        ] : null,
                        'created_at' => $order->created_at?->toDateTimeString(),
                        'items_count' => $order->items ? $order->items->count() : 0,
                        'items' => $order->items ? $order->items->map(function($item) {
                            return [
                                'id' => $item->id,
                                'garment_name' => $item->garment_name ?? $item->garment?->name ?? 'Garment',
                                'status' => is_object($item->status) && isset($item->status->value) ? $item->status->value : (is_object($item->status) ? $item->status->name : $item->status),
                                'price' => $item->price,
                                'quantity' => $item->quantity,
                            ];
                        }) : [],
                    ];
                });
            }),
            'appointments' => $this->when($includeDetails && ($this->role === \App\Enums\RoleEnum::CUSTOMER || (is_object($this->role) ? $this->role->value : $this->role) === 'customer' || \App\Models\Appointment::where('customer_id', $this->id)->exists()), function() {
                $appointments = \App\Models\Appointment::with('assignedStaff')
                    ->where('customer_id', $this->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $appointments->map(function($app) {
                    return [
                        'id' => $app->id,
                        'type' => $app->type,
                        'appointment_date' => $app->appointment_date instanceof \DateTimeInterface ? $app->appointment_date->toDateString() : $app->appointment_date,
                        'appointment_time' => $app->appointment_time,
                        'status' => is_object($app->status) && isset($app->status->value) ? $app->status->value : (is_object($app->status) ? $app->status->name : $app->status),
                        'visit_charge' => $app->visit_charge,
                        'payment_status' => is_object($app->payment_status) && isset($app->payment_status->value) ? $app->payment_status->value : (is_object($app->payment_status) ? $app->payment_status->name : $app->payment_status),
                        'assigned_staff' => $app->assignedStaff ? [
                            'id' => $app->assignedStaff->id,
                            'name' => $app->assignedStaff->name,
                            'phone' => $app->assignedStaff->phone,
                        ] : null,
                        'gender' => $app->gender,
                        'female_tailor_visit' => $app->female_tailor_visit,
                        'has_fabric' => $app->has_fabric,
                        'measurement_type' => $app->measurement_type,
                        'address' => $app->formatted_address,
                        'created_at' => $app->created_at?->toDateTimeString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
