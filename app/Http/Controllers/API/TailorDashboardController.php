<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\TailorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Barryvdh\DomPDF\Facade\Pdf;

class TailorDashboardController extends Controller
{
    /**
     * Tailor Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim($request->email);

        $user = User::where(function ($query) use ($loginInput) {
            $query->where('email', $loginInput)
                  ->orWhere('phone', $loginInput);

            $cleaned = preg_replace('/[^0-9]/', '', $loginInput);
            if (strlen($cleaned) >= 10) {
                $query->orWhere('phone', 'like', '%' . substr($cleaned, -10));
            }
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email/phone or password.',
            ], 401);
        }

        $roleVal = is_object($user->role) ? $user->role->value : $user->role;
        if ($roleVal !== 'tailor' && $roleVal !== 'TLR') {
            return response()->json([
                'success' => false,
                'message' => 'This account does not have tailor access.',
            ], 403);
        }

        if ($user->status === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => 'Your tailor account has been blocked. Please contact the administrator.',
            ], 403);
        }

        $token = $user->createToken('tailor_token')->plainTextToken;
        $profile = TailorProfile::firstOrCreate(['user_id' => $user->id]);

        $metrics = [
            'total_assigned' => OrderItem::where('assigned_tailor_id', $user->id)->count(),
            'completed' => OrderItem::where('assigned_tailor_id', $user->id)->where('status', 'completed')->count(),
            'stitching' => OrderItem::where('assigned_tailor_id', $user->id)->where('status', 'stitching')->count(),
            'pending' => OrderItem::where('assigned_tailor_id', $user->id)->whereIn('status', ['pending', 'assigned'])->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Tailor login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => 'tailor',
                    'status' => $user->status,
                    'tailor_profile' => $profile,
                    'tailor_metrics' => $metrics,
                ]
            ]
        ]);
    }

    /**
     * Tailor Dashboard overview statistics
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $tailorId = $user->id;

        $tailorProfile = TailorProfile::firstOrCreate(['user_id' => $tailorId]);

        $totalAssigned = OrderItem::where('assigned_tailor_id', $tailorId)->count();
        $completed = OrderItem::where('assigned_tailor_id', $tailorId)->where('status', 'completed')->count();
        $stitching = OrderItem::where('assigned_tailor_id', $tailorId)->where('status', 'stitching')->count();
        $pending = OrderItem::where('assigned_tailor_id', $tailorId)->whereIn('status', ['pending', 'assigned'])->count();

        // Urgent items: delivery_date within next 3 days or overdue
        $urgentItemsCount = OrderItem::where('assigned_tailor_id', $tailorId)
            ->where('status', '!=', 'completed')
            ->whereHas('order', function ($q) {
                $q->whereNotNull('delivery_date')
                  ->where('delivery_date', '<=', now()->addDays(3)->toDateString());
            })->count();

        $recentItems = OrderItem::with(['order', 'garment.category'])
            ->where('assigned_tailor_id', $tailorId)
            ->latest()
            ->take(6)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number ?? ('#' . $item->order_id),
                'garment_name' => $item->garment_name,
                'garment_category' => $item->garment?->category?->name,
                'status' => $item->status,
                'delivery_date' => $item->order?->delivery_date?->toDateString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => 'tailor',
                ],
                'tailor_profile' => $tailorProfile,
                'metrics' => [
                    'total_assigned' => $totalAssigned,
                    'completed' => $completed,
                    'stitching' => $stitching,
                    'pending' => $pending,
                    'urgent' => $urgentItemsCount,
                ],
                'recent_items' => $recentItems,
            ]
        ]);
    }

    /**
     * List all assigned items for this tailor with filtering and search
     */
    public function assignedItems(Request $request)
    {
        $tailorId = $request->user()->id;
        $status = $request->query('status');
        $search = $request->query('search');

        $query = OrderItem::with([
            'order.customer',
            'order.measurements',
            'order.appointment.uploads',
            'garment.category',
            'garment.design'
        ])->where('assigned_tailor_id', $tailorId);

        if ($status && $status !== 'all') {
            if ($status === 'pending') {
                $query->whereIn('status', ['pending', 'assigned']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('garment_name', 'like', "%{$search}%")
                  ->orWhere('custom_notes', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                         ->orWhereHas('customer', function ($cq) use ($search) {
                             $cq->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $items = $query->latest()->get();

        $formatted = $items->map(function ($item) {
            $orderMeasurements = $item->order ? $item->order->measurements : collect();
            $itemMeasurements = $orderMeasurements->filter(function ($m) use ($item) {
                return ($m->order_item_id && $m->order_item_id == $item->id) || ($m->garment_id == $item->garment_id);
            })->values();

            return [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number ?? ('#' . $item->order_id),
                'order_status' => is_object($item->order?->status) ? $item->order?->status->value : $item->order?->status,
                'delivery_date' => $item->order?->delivery_date?->toDateString(),
                'garment_id' => $item->garment_id,
                'garment_name' => $item->garment_name,
                'garment_category' => $item->garment?->category?->name,
                'garment_image' => $item->garment?->image,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'status' => $item->status,
                'provided_own_fabric' => (bool) $item->provided_own_fabric,
                'custom_notes' => $item->custom_notes,
                'old_cloth_photo' => $item->old_cloth_photo,
                'old_cloth_photo_url' => $item->old_cloth_photo_url,
                'new_cloth_photo' => $item->new_cloth_photo,
                'new_cloth_photo_url' => $item->new_cloth_photo_url,
                'clothes' => $item->clothes,
                'appointment_photos' => $item->order?->appointment?->uploads ? $item->order->appointment->uploads->map(fn($u) => $u->image_url)->filter()->values() : [],
                'measurements' => $itemMeasurements,
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Show single assigned item in detail
     */
    public function showItem(Request $request, $id)
    {
        $tailorId = $request->user()->id;
        $item = OrderItem::with([
            'order.measurements',
            'order.appointment.uploads',
            'garment.category',
            'garment.design'
        ])->where('id', $id)
          ->where('assigned_tailor_id', $tailorId)
          ->firstOrFail();

        $orderMeasurements = $item->order ? $item->order->measurements : collect();
        $itemMeasurements = $orderMeasurements->filter(function ($m) use ($item) {
            return ($m->order_item_id && $m->order_item_id == $item->id) || ($m->garment_id == $item->garment_id);
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'order_number' => $item->order?->order_number ?? ('#' . $item->order_id),
                'order_status' => is_object($item->order?->status) ? $item->order?->status->value : $item->order?->status,
                'delivery_date' => $item->order?->delivery_date?->toDateString(),
                'garment_id' => $item->garment_id,
                'garment_name' => $item->garment_name,
                'garment_category' => $item->garment?->category?->name,
                'garment_image' => $item->garment?->image,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'status' => $item->status,
                'provided_own_fabric' => (bool) $item->provided_own_fabric,
                'custom_notes' => $item->custom_notes,
                'old_cloth_photo' => $item->old_cloth_photo,
                'old_cloth_photo_url' => $item->old_cloth_photo_url,
                'new_cloth_photo' => $item->new_cloth_photo,
                'new_cloth_photo_url' => $item->new_cloth_photo_url,
                'clothes' => $item->clothes,
                'appointment_photos' => $item->order?->appointment?->uploads ? $item->order->appointment->uploads->map(fn($u) => $u->image_url)->filter()->values() : [],
                'measurements' => $itemMeasurements,
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Update assignment item status (pending, stitching, completed)
     */
    public function updateItemStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,assigned,stitching,completed',
        ]);

        $tailorId = $request->user()->id;
        $item = OrderItem::where('id', $id)
            ->where('assigned_tailor_id', $tailorId)
            ->firstOrFail();

        $item->update(['status' => $validated['status']]);

        // If item status is moved to stitching and order is currently assigned, sync order status
        if ($validated['status'] === 'stitching' && $item->order) {
            $orderStatus = is_object($item->order->status) ? $item->order->status->value : $item->order->status;
            if (in_array($orderStatus, ['assigned', 'pending'])) {
                $item->order->update(['status' => 'stitching']);
            }
        }

        // Check if all items in the order are completed
        if ($validated['status'] === 'completed' && $item->order) {
            $allCompleted = $item->order->items()->where('status', '!=', 'completed')->count() === 0;
            if ($allCompleted) {
                $orderStatus = is_object($item->order->status) ? $item->order->status->value : $item->order->status;
                if ($orderStatus === 'stitching') {
                    $item->order->update(['status' => 'ready']);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment status updated to ' . $validated['status'],
            'data' => $item
        ]);
    }

    /**
     * Toggle tailor availability status (Available / On Break)
     */
    public function toggleAvailability(Request $request)
    {
        $tailorId = $request->user()->id;
        $profile = TailorProfile::firstOrCreate(['user_id' => $tailorId]);

        $isAvailable = $request->has('is_available')
            ? filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN)
            : !$profile->is_available;

        $profile->update(['is_available' => $isAvailable]);

        return response()->json([
            'success' => true,
            'message' => $isAvailable ? 'You are now marked as Available for new work' : 'You are now marked as On Break / Unavailable',
            'data' => [
                'is_available' => $profile->is_available,
            ]
        ]);
    }

    /**
     * Download Stitching Slip for an item directly from Tailor Portal
     */
    public function downloadStitchingSlip(Request $request, $id)
    {
        $tailorId = $request->user()->id;
        $item = OrderItem::where('id', $id)
            ->where('assigned_tailor_id', $tailorId)
            ->firstOrFail();

        $order = \App\Models\Order::with(['customer', 'items.garment', 'items.tailor', 'measurements'])
            ->findOrFail($item->order_id);

        $order->setRelation('items', collect([$item]));

        $filteredMeasurements = $order->measurements->filter(function ($m) use ($item) {
            return ($m->order_item_id == $item->id) || ($m->garment_id == $item->garment_id);
        });
        $order->setRelation('measurements', $filteredMeasurements);

        $pdf = Pdf::loadView('invoices.stitching_slip', [
            'order' => $order,
            'selectedItem' => $item,
        ]);

        $fileName = 'stitching-slip-' . $order->order_number . '-' . str_replace(' ', '-', strtolower($item->garment_name)) . '.pdf';

        return $pdf->download($fileName);
    }
}
