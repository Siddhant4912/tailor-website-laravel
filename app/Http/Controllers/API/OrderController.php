<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Http\Resources\OrderResource;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\OrderStatusEnum;

class OrderController extends Controller
{
    public function __construct(protected OrderService $service) {

    
    }
    

    public function index(Request $request)
    {
        $query = Order::with(['customer', 'items.garment', 'items.tailor', 'measurements', 'invoices.transactions'])
            ->where('status', '!=', \App\Enums\OrderStatusEnum::Draft);
        
        // If customer, only see own orders
        if ($request->user() && $request->user()->role->value === 'customer') {
            $query->where('customer_id', $request->user()->id);
        }

        // Add filters here if needed
        
        return $this->successResponse(OrderResource::collection($query->latest()->get()), 'Orders fetched');
    }

    public function show(Request $request, int $id)
    {
        $order = Order::with([
            'customer',
            'items.garment',
            'items.tailor',
            'measurements',
            'invoices.transactions',
            'statusLogs',
            'deliveryStaff'
        ])->findOrFail($id);

        $user = $request->user();
        if ($user->isCustomer() && $order->customer_id !== $user->id) {
            return $this->errorResponse('Unauthorized', 403);
        }
        if ($user->isDeliveryStaff() && $order->delivery_staff_id !== $user->id && $order->pickup_staff_id !== $user->id) {
            return $this->errorResponse('Unauthorized', 403);
        }
        if ($user->isTailor()) {
            $hasAssignedItem = $order->items()->where('assigned_tailor_id', $user->id)->exists();
            if (!$hasAssignedItem) {
                return $this->errorResponse('Unauthorized', 403);
            }
        }
                      
        return $this->successResponse(new OrderResource($order), 'Order fetched');
    }

    public function createFromAppointment(Request $request, int $appointmentId)
    {
        $appointment = \App\Models\Appointment::findOrFail($appointmentId);
        $user = $request->user();

        if ($user->isCustomer() && $appointment->customer_id !== $user->id) {
            return $this->errorResponse('Unauthorized', 403);
        }
        if ($user->isDeliveryStaff() && $appointment->assigned_staff_id !== $user->id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validated = $request->validate([
            'delivery_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.garment_id' => 'required|exists:garments,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.custom_notes' => 'nullable|string',
            'items.*.measurements' => 'nullable|array',
            'items.*.measurements.*.field_name' => 'required|string',
            'items.*.measurements.*.value' => 'required|string',
        ]);

        $order = $this->service->createFromAppointment($appointmentId, $validated);

        return $this->successResponse(new OrderResource($order), 'Order created successfully from appointment', 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->isCustomer()) {
            return $this->errorResponse('Forbidden', 403);
        }

        $order = Order::findOrFail($id);
        if ($user->isDeliveryStaff()) {
            if ($order->pickup_staff_id != $user->id && $order->delivery_staff_id != $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }
        }
        if ($user->isTailor()) {
            $hasAssignedItem = $order->items()->where('assigned_tailor_id', $user->id)->exists();
            if (!$hasAssignedItem) {
                return $this->errorResponse('Unauthorized', 403);
            }
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,accepted,assigned,stitching,ready,out_for_delivery,delivered,cancelled',
            'remarks' => 'nullable|string',
            'otp' => 'nullable|string',
            'payment_mode' => 'nullable|string|in:cash,upi,card',
        ]);

        try {
            $enumStatus = OrderStatusEnum::tryFrom($validated['status']);
            
            $order = $this->service->updateStatus(
                $id,
                $enumStatus,
                $request->user()->id,
                $validated['remarks'] ?? null,
                $validated['otp'] ?? null,
                $validated['payment_mode'] ?? null
            );

            return $this->successResponse(new OrderResource($order), 'Order status updated');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
    public function store(StoreOrderRequest $request)
    {
        try {
            $validated = $request->validated();
            
            $customerId = $request->user()->id;
            if ($request->user()->isAdmin()) {
                $customerId = $request->input('customer_id') ?? $request->user()->id;
            }
            
            // This is a direct order (no appointment)
            $order = $this->service->createDirectOrder($validated, $customerId);

            return $this->successResponse(new OrderResource($order), 'Order created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create order', 500, $e->getMessage());
        }
    }


    public function assignTailor(Request $request, int $orderId, int $itemId)
{
    $validated = $request->validate([
        'tailor_id' => 'required|exists:users,id',
    ]);

    $tailor = \App\Models\User::where('id', $validated['tailor_id'])
        ->where('role', 'tailor')
        ->first();

    if (!$tailor) {
        return $this->errorResponse(
            'Selected user is not a tailor',
            422
        );
    }

    $item = \App\Models\OrderItem::where('order_id', $orderId)
        ->where('id', $itemId)
        ->firstOrFail();

    $item->update([
        'assigned_tailor_id' => $validated['tailor_id'],
        'status' => 'assigned',
    ]);

    $item->load('tailor');

    return $this->successResponse(
        $item,
        'Tailor assigned successfully'
    );
}

   public function assignDelivery(Request $request, int $orderId)
{
    try {

        // 1. Validate input
        $validated = $request->validate([
            'delivery_staff_id' => 'required|exists:users,id',
        ]);

        // 2. Get user safely
        $deliveryBoy = \App\Models\User::find($validated['delivery_staff_id']);

        // 3. Check role properly (IMPORTANT FIX)
        $roleVal = is_object($deliveryBoy->role) ? $deliveryBoy->role->value : $deliveryBoy->role;
        if ($roleVal !== 'delivery_staff') {
            return $this->errorResponse(
                'Selected user is not delivery staff',
                422
            );
        }

        // 4. Find order
        $order = \App\Models\Order::findOrFail($orderId);

        // 5. Update order
        $order->update([
            'delivery_staff_id' => $validated['delivery_staff_id'],
        ]);

        // 6. Reload relations properly
        $order->load(['deliveryStaff', 'customer', 'items']);

        // 7. Return fresh response
        return $this->successResponse(
            $order->fresh(),
            'Delivery staff assigned successfully'
        );

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'Assignment failed',
            'error' => $e->getMessage()
        ], 500);
    }
}


    // POST /api/orders/{id}/approve
    public function approve(Request $request, $id)
    {
        try {
            $order = \App\Models\Order::where('id', $id)
                        ->where('customer_id', $request->user()->id)
                        ->firstOrFail();
            
            $statusStr = is_object($order->status) ? $order->status->value : (string) $order->status;

            if ($statusStr !== 'draft') {
                return response()->json(['message' => 'This order is already processed.'], 400);
            }

            // 1. Order chi status 'pending' (confirm) kara
            $order->update(['status' => 'pending']);

            // Dispatch notification
            try {
                $order->customer->notify(new \App\Notifications\OrderStatusNotification($order, 'pending'));
            } catch (\Throwable $e) {
                \Log::error('Order approval notification failed: ' . $e->getMessage());
            }

            // 2. YETHE INVOICE GENERATE KARA:
            try {
                app(\App\Services\InvoiceService::class)->generateForOrder($order);
            } catch (\Throwable $e) {
                \Log::error('Invoice generate failed: ' . $e->getMessage());
            }

            return response()->json(['message' => 'Order approved and invoice generated successfully', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to approve order'], 500);
        }
    }

    public function downloadStitchingSlip(Request $request, $id)
    {
        try {
            $order = Order::with(['customer', 'items.garment', 'items.tailor', 'measurements'])->findOrFail($id);
            $user = $request->user();

            if ($user && !$user->isAdmin()) {
                if ($user->isCustomer() && $order->customer_id !== $user->id) {
                    return $this->errorResponse('Unauthorized', 403);
                }
                if ($user->isDeliveryStaff() && $order->delivery_staff_id !== $user->id && $order->pickup_staff_id !== $user->id) {
                    return $this->errorResponse('Unauthorized', 403);
                }
                if ($user->isTailor()) {
                    $hasAssignedItem = $order->items()->where('assigned_tailor_id', $user->id)->exists();
                    if (!$hasAssignedItem) {
                        return $this->errorResponse('Unauthorized', 403);
                    }
                }
            }

            $itemId = $request->query('item_id');
            $selectedItem = null;

            if ($itemId) {
                // Find the specific item in the collection
                $selectedItem = $order->items->firstWhere('id', (int) $itemId);
                if ($selectedItem) {
                    // Filter order items collection
                    $order->setRelation('items', collect([$selectedItem]));

                    // Filter order measurements collection to only contain measurements matching this order_item_id or garment_id
                    $filteredMeasurements = $order->measurements->filter(function($m) use ($itemId, $selectedItem) {
                        return ($m->order_item_id == $itemId) || ($m->garment_id == $selectedItem->garment_id);
                    });
                    $order->setRelation('measurements', $filteredMeasurements);
                }
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.stitching_slip', [
                'order' => $order,
                'selectedItem' => $selectedItem,
            ]);

            $fileName = $selectedItem 
                ? 'stitching-slip-' . $order->order_number . '-' . str_replace(' ', '-', strtolower($selectedItem->garment_name)) . '.pdf'
                : 'stitching-slip-' . $order->order_number . '.pdf';

            return $pdf->download($fileName);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download stitching slip',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            $user = $request->user();
            if (!$user->isAdmin() && $order->customer_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }
            $order->invoices->each(function($invoice) {
                $invoice->transactions()->delete();
                $invoice->delete();
            });
            $order->delete();
            return $this->successResponse(null, 'Order deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function payAndCreate(Request $request)
    {
        try {
            $orderRequest = new \App\Http\Requests\StoreOrderRequest();
            $rules = $orderRequest->rules();
            $nestedRules = [];
            foreach ($rules as $key => $rule) {
                $nestedRules['order.' . $key] = $rule;
            }

            $validatedData = $request->validate(array_merge([
                'payment.razorpay_payment_id' => 'required|string',
                'payment.razorpay_order_id' => 'required|string',
                'payment.razorpay_signature' => 'required|string',
                'order' => 'required|array',
            ], $nestedRules));

            $payment = $validatedData['payment'];
            $orderData = $validatedData['order'];

            // 1. Verify Razorpay Signature
            $keySecret = config('services.razorpay.key_secret');
            if (empty($keySecret)) {
                return $this->errorResponse('Razorpay credentials not configured on server.', 500);
            }

            $expectedSignature = hash_hmac(
                'sha256',
                $payment['razorpay_order_id'] . '|' . $payment['razorpay_payment_id'],
                $keySecret
            );

            if (!hash_equals($expectedSignature, $payment['razorpay_signature'])) {
                return $this->errorResponse('Invalid Razorpay signature. Verification failed.', 400);
            }

            // 2. Run inside DB transaction
            $order = \DB::transaction(function () use ($orderData, $payment, $request) {
                $customerId = $request->user()->id;
                if ($request->user()->isAdmin() && isset($orderData['customer_id'])) {
                    $customerId = $orderData['customer_id'];
                }

                // Force status to PENDING
                $orderData['status'] = 'pending';
                $orderData['payment_method'] = 'online';

                $createdOrder = $this->service->createDirectOrder($orderData, $customerId);

                // Fetch generated invoice and mark it paid, and its transactions successful
                $invoice = $createdOrder->invoices()->first();
                if ($invoice) {
                    $invoice->update([
                        'status' => \App\Enums\InvoiceStatusEnum::PAID,
                        'paid_at' => now(),
                    ]);

                    $invoice->transactions()->update([
                        'status' => \App\Enums\TransactionStatusEnum::SUCCESSFUL,
                        'payment_mode' => 'online',
                        'transaction_number' => $payment['razorpay_payment_id'],
                        'gateway_response' => $payment,
                    ]);
                }

                return $createdOrder;
            });

            return $this->successResponse(new OrderResource($order), 'Order created and payment verified', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create order with payment', 500, $e->getMessage());
        }
    }

    public function initiateOrderPayment(Request $request)
    {
        try {
            $orderRequest = new \App\Http\Requests\StoreOrderRequest();
            $rules = $orderRequest->rules();
            $nestedRules = [];
            foreach ($rules as $key => $rule) {
                $nestedRules['order.' . $key] = $rule;
            }

            $validatedData = $request->validate(array_merge([
                'order' => 'required|array',
            ], $nestedRules));

            $orderData = $validatedData['order'];

            $amount = 0;

            // Run inside a transaction that we will rollback
            \DB::beginTransaction();
            try {
                $customerId = $request->user()->id;
                if ($request->user()->isAdmin() && isset($orderData['customer_id'])) {
                    $customerId = $orderData['customer_id'];
                }

                $createdOrder = $this->service->createDirectOrder($orderData, $customerId);
                $invoice = $createdOrder->invoices()->first();
                if ($invoice) {
                    $txn = $invoice->transactions()->where('status', 'pending')->first();
                    if ($txn) {
                        $amount = $txn->amount;
                    }
                }
            } finally {
                \DB::rollBack(); // Always rollback!
            }

            if ($amount <= 0) {
                return $this->errorResponse('Transaction amount is 0. No payment required.', 400);
            }

            // Create Razorpay order
            $amountInPaise = round($amount * 100);
            $keyId = config('services.razorpay.key_id');
            $keySecret = config('services.razorpay.key_secret');

            if (empty($keyId) || empty($keySecret)) {
                return $this->errorResponse('Razorpay credentials not configured on server.', 500);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => 'init_' . uniqid(),
            ]));
            curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Failed to create Razorpay order from gateway', $httpCode, $result);
            }

            $rzpOrder = json_decode($result, true);

            return $this->successResponse([
                'razorpay_order_id' => $rzpOrder['id'],
                'amount' => $amount,
                'currency' => 'INR',
                'key_id' => $keyId,
            ], 'Razorpay order initiated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initiate Razorpay order', 500, $e->getMessage());
        }
    }
}
