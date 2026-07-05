<?php
// siddhant pawar : 04-07-2026
// siddhant pawawr 05-07-2026

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Appointment;
use App\Models\Transaction;
use App\Models\PaymentAuditLog;
use App\Enums\InvoiceStatusEnum;
use App\Enums\TransactionStatusEnum;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService)
    {
    }

    public function index(Request $request)
    {
        $invoices = Invoice::with(['invoiceable', 'customer'])->latest()->get();

        // FIX #5: wrap in InvoiceResource instead of returning raw model
        return $this->successResponse(InvoiceResource::collection($invoices), 'Invoices fetched');
    }

    public function generate($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            $invoice = $this->invoiceService->generateForOrder($order);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function downloadInvoiceByOrder(Request $request, $orderId)
    {
        $invoice = Invoice::where('invoiceable_type', Order::class)
            ->where('invoiceable_id', $orderId)
            ->first();

        if (!$invoice) {
            $order = Order::find($orderId);
            if ($order) {
                try {
                    $invoice = $this->invoiceService->generateForOrder($order);
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Failed to generate invoice: ' . $e->getMessage()], 500);
                }
            }
        }

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        return $this->downloadInvoice($request, $invoice->id);
    }

    public function downloadInvoice(Request $request, $id)
    {
        // 1. Try finding by Invoice ID directly
        $invoice = Invoice::with(['invoiceable', 'customer', 'transactions'])->find($id);

        if (!$invoice) {
            // 2. Try finding by Order ID
            $invoice = Invoice::where('invoiceable_type', Order::class)
                ->where('invoiceable_id', $id)
                ->first();

            if (!$invoice) {
                // Try finding the order to auto-generate
                $order = Order::find($id);
                if ($order) {
                    $invoice = $this->invoiceService->generateForOrder($order);
                }
            }
        }

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        // Reload relations depending on invoiceable type
        if ($invoice->invoiceable_type === Order::class) {
            $invoice->load(['invoiceable.items.garment.design', 'invoiceable.items.garment.category', 'customer', 'transactions']);
        } elseif ($invoice->invoiceable_type === Appointment::class) {
            $invoice->load(['invoiceable.items.garment.design', 'invoiceable.items.garment.category', 'customer', 'transactions']);
        }

        $user = $request->user();
        $isAdmin = false;
        if ($user) {
            $roleVal = is_object($user->role) ? $user->role->value : $user->role;
            if ($roleVal === 'admin' || $roleVal === 'ADM') {
                $isAdmin = true;
            }
        }

        if (!$isAdmin && $user) {
            $invoiceCustomerId = $invoice->customer_id ?? $invoice->invoiceable?->customer_id;
            if ($user->isCustomer() && $invoiceCustomerId != $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($user->isDeliveryStaff()) {
                if ($invoice->invoiceable_type === Order::class) {
                    $order = $invoice->invoiceable;
                    if ($order->delivery_staff_id != $user->id && $order->pickup_staff_id != $user->id) {
                        return response()->json(['message' => 'Unauthorized'], 403);
                    }
                } elseif ($invoice->invoiceable_type === Appointment::class) {
                    $appointment = $invoice->invoiceable;
                    if ($appointment->assigned_staff_id != $user->id) {
                        return response()->json(['message' => 'Unauthorized'], 403);
                    }
                }
            }
        }

        if ($invoice->invoiceable_type === Appointment::class) {
            $order = $invoice->invoiceable->order()->first();
            if ($order) {
                $orderInvoice = $order->invoices()->first();
                if ($orderInvoice) {
                    $invoice = $orderInvoice;
                    $invoice->load(['invoiceable.items.garment.design', 'invoiceable.items.garment.category', 'customer', 'transactions']);
                }
            }
        }

        $invoiceable = $invoice->invoiceable;

        if ($invoice->invoiceable_type === Order::class) {
            $pdf = Pdf::loadView('invoices.invoice', [
                'order' => $invoiceable,
                'invoice' => $invoice,
            ]);
            $filename = 'invoice-' . ($invoiceable->order_number ?? $invoice->invoice_number) . '.pdf';
        } else {
            $pdf = Pdf::loadView('invoices.appointment', [
                'appointment' => $invoiceable,
                'invoice' => $invoice,
            ]);
            $filename = 'appointment-invoice-' . $invoice->invoice_number . '.pdf';
        }

        return $pdf->download($filename);
    }

    public function transactions(Request $request)
    {
        try {
            // Stats calculations:
            // 1. Total Earnings (successful transaction total amount)
            $totalEarnings = floatval(Transaction::where('status', TransactionStatusEnum::SUCCESSFUL)->sum('amount'));

            // 2. Pending Balance (pending transaction total amount)
            $pendingBalance = floatval(Transaction::where('status', TransactionStatusEnum::PENDING)->sum('amount'));

            // 3. GST Collected (calculated proportionally on successful transactions)
            $successfulTxnsForGst = Transaction::where('status', TransactionStatusEnum::SUCCESSFUL)
                ->with(['invoice'])
                ->get();
            $totalGstCollected = 0;
            foreach ($successfulTxnsForGst as $txn) {
                $totalAmount = floatval($txn->invoice?->total_amount ?? 0);
                $gstAmount = floatval($txn->invoice?->gst_amount ?? 0);
                if ($totalAmount > 0) {
                    $totalGstCollected += floatval($txn->amount) * ($gstAmount / $totalAmount);
                }
            }

            // Build base list query
            $listQuery = Transaction::with([
                'invoice.customer', 
                'invoice.invoiceable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Appointment::class => ['assignedStaff'],
                        \App\Models\Order::class => ['pickupStaff', 'deliveryStaff'],
                    ]);
                }
            ]);

            // Exclude pending cash transactions (they haven't been collected by staff yet)
            $listQuery->where(function ($q) {
                $q->where('status', '!=', TransactionStatusEnum::PENDING)
                    ->orWhere('payment_mode', '!=', 'cash');
            });

            // Apply search filter (transaction number, invoice number, customer name, phone)
            if ($request->filled('search')) {
                $search = $request->input('search');
                $listQuery->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                        ->orWhereHas('invoice', function ($qi) use ($search) {
                            $qi->where('invoice_number', 'like', "%{$search}%")
                                ->orWhereHas('customer', function ($qc) use ($search) {
                                    $qc->where('name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                });
                        });
                });
            }

            // Apply mode filter
            if ($request->filled('mode') && $request->input('mode') !== 'all') {
                $listQuery->where('payment_mode', $request->input('mode'));
            }

            // Apply status filter
            if ($request->filled('status') && $request->input('status') !== 'all') {
                $listQuery->where('status', $request->input('status'));
            }

            $perPage = $request->input('per_page', 10);
            $paginated = $listQuery->latest()->paginate($perPage);

            return $this->successResponse([
                'paginated' => $paginated,
                'stats' => [
                    'total_earnings' => $totalEarnings,
                    'total_gst_collected' => $totalGstCollected,
                    'pending_balance' => $pendingBalance,
                ]
            ], 'Transactions fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch transactions', 500, $e->getMessage());
        }
    }

    public function markPaid(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'received_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            $transaction = Transaction::findOrFail($id);

            $receivedAmount = $request->input('received_amount', $transaction->amount);
            $notes = $request->input('notes');

            // Store note and received amount in gateway_response JSON
            $gatewayResponse = $transaction->gateway_response ?? [];
            $gatewayResponse['received_amount'] = $receivedAmount;
            $gatewayResponse['admin_notes'] = $notes;
            $gatewayResponse['verified_by_admin_at'] = now()->toIso8601String();

            $transaction->update([
                'amount' => $receivedAmount,
                'status' => TransactionStatusEnum::SUCCESSFUL,
                'gateway_response' => $gatewayResponse,
            ]);

            $invoice = $transaction->invoice;

            // Check if all transactions for this invoice are successful
            $allSuccessful = !$invoice->transactions()
                ->where('status', '!=', TransactionStatusEnum::SUCCESSFUL->value)
                ->exists();

            if ($allSuccessful) {
                $invoice->update([
                    'status' => InvoiceStatusEnum::PAID,
                    'paid_at' => now(),
                ]);
            }

            // Record this verification in the immutable audit logs
            PaymentAuditLog::create([
                'customer_id' => $invoice->customer_id,
                'staff_id' => $request->user()->id, // admin
                'loggable_type' => Transaction::class,
                'loggable_id' => $transaction->id,
                'type' => 'settlement',
                'amount_collected' => $receivedAmount,
                'amount_submitted' => $receivedAmount,
                'status' => 'success',
                'admin_verification_details' => 'Admin verified manual payment. Notes: ' . $notes,
            ]);

            return $this->successResponse($transaction->load('invoice.customer'), 'Payment marked as successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark payment as successful', 500, $e->getMessage());
        }
    }

    public function mockCharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|exists:transactions,id',
                'payment_mode' => 'required|string',
            ]);

            $txn = Transaction::with('invoice')->findOrFail($validated['transaction_id']);
            $user = $request->user();
            if ($user && !$user->isAdmin() && $txn->invoice->customer_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $txn->update([
                'payment_mode' => $validated['payment_mode'],
                'status' => TransactionStatusEnum::SUCCESSFUL,
                'transaction_number' => 'MOCK-' . strtoupper(Str::random(10)),
                'gateway_response' => [
                    'message' => 'Simulated successful checkout (No live credentials)',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            $invoice = $txn->invoice;

            // Check if all transactions for this invoice are successful
            $allSuccessful = !$invoice->transactions()
                ->where('status', '!=', TransactionStatusEnum::SUCCESSFUL->value)
                ->exists();

            if ($allSuccessful) {
                $invoice->update([
                    'status' => InvoiceStatusEnum::PAID,
                    'paid_at' => now(),
                ]);

                $invoiceable = $invoice->invoiceable;
                if ($invoiceable) {
                    $statusVal = is_object($invoiceable->status) ? $invoiceable->status->value : (string) $invoiceable->status;
                    if ($statusVal === 'draft') {
                        if ($invoiceable instanceof Appointment) {
                            $invoiceable->update(['status' => 'pending']);
                            // Dispatch notification
                            try {
                                $invoiceable->customer->notify(new \App\Notifications\AppointmentStatusNotification($invoiceable, 'pending'));
                            } catch (\Throwable $e) {
                                \Log::error('Appointment mock verification notification failed: ' . $e->getMessage());
                            }
                        } elseif ($invoiceable instanceof Order) {
                            $invoiceable->update(['status' => \App\Enums\OrderStatusEnum::PENDING]);
                            // Dispatch notification
                            try {
                                $invoiceable->customer->notify(new \App\Notifications\OrderStatusNotification($invoiceable, 'pending'));
                            } catch (\Throwable $e) {
                                \Log::error('Order mock verification notification failed: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            return $this->successResponse($txn->load('invoice.customer'), 'Payment simulated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process mock charge', 500, $e->getMessage());
        }
    }

    public function createRazorpayOrder(Request $request, $transactionId)
    {
        try {
            $user = $request->user();
            $txn = \App\Models\Transaction::with('invoice')->findOrFail($transactionId);

            if (!$user->isAdmin() && !$user->isDeliveryStaff() && $txn->invoice->customer_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }

            if ($txn->status === TransactionStatusEnum::SUCCESSFUL) {
                return $this->errorResponse('Transaction is already paid.', 400);
            }

            $amountInPaise = round($txn->amount * 100);
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
                'receipt' => 'txn_' . $txn->id,
            ]));
            curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse('Failed to create order with Razorpay: ' . $result, 500);
            }

            $rzpOrder = json_decode($result, true);
            $rzpOrderId = $rzpOrder['id'];

            $gatewayResponse = $txn->gateway_response ?? [];
            $gatewayResponse['razorpay_order_id'] = $rzpOrderId;

            $txn->update([
                'gateway_response' => $gatewayResponse,
            ]);

            return $this->successResponse([
                'razorpay_order_id' => $rzpOrderId,
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'key_id' => $keyId,
                'transaction_id' => $txn->id,
            ], 'Razorpay order created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create Razorpay order', 500, $e->getMessage());
        }
    }

    public function verifyRazorpayPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|exists:transactions,id',
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $txn = \App\Models\Transaction::with('invoice.invoiceable')->findOrFail($validated['transaction_id']);

            if ($txn->status === TransactionStatusEnum::SUCCESSFUL) {
                return $this->successResponse($txn, 'Payment already verified');
            }

            $keySecret = config('services.razorpay.key_secret');
            if (empty($keySecret)) {
                return $this->errorResponse('Razorpay credentials not configured on server.', 500);
            }

            $expectedSignature = hash_hmac(
                'sha256',
                $validated['razorpay_order_id'] . '|' . $validated['razorpay_payment_id'],
                $keySecret
            );

            if (!hash_equals($expectedSignature, $validated['razorpay_signature'])) {
                return $this->errorResponse('Invalid Razorpay signature. Verification failed.', 400);
            }

            $gatewayResponse = $txn->gateway_response ?? [];
            $gatewayResponse['razorpay_payment_id'] = $validated['razorpay_payment_id'];
            $gatewayResponse['razorpay_signature'] = $validated['razorpay_signature'];

            $txn->update([
                'payment_mode' => 'online',
                'status' => TransactionStatusEnum::SUCCESSFUL,
                'transaction_number' => $validated['razorpay_payment_id'],
                'gateway_response' => $gatewayResponse,
            ]);

            $invoice = $txn->invoice;
            $allSuccessful = !$invoice->transactions()
                ->where('status', '!=', TransactionStatusEnum::SUCCESSFUL->value)
                ->exists();

            if ($allSuccessful) {
                $invoice->update([
                    'status' => InvoiceStatusEnum::PAID,
                    'paid_at' => now(),
                ]);

                $invoiceable = $invoice->invoiceable;
                if ($invoiceable) {
                    $statusVal = is_object($invoiceable->status) ? $invoiceable->status->value : (string) $invoiceable->status;
                    if ($statusVal === 'draft') {
                        if ($invoiceable instanceof Appointment) {
                            $invoiceable->update(['status' => 'pending']);
                            // Dispatch notification
                            try {
                                $invoiceable->customer->notify(new \App\Notifications\AppointmentStatusNotification($invoiceable, 'pending'));
                            } catch (\Throwable $e) {
                                \Log::error('Appointment verification notification failed: ' . $e->getMessage());
                            }
                        } elseif ($invoiceable instanceof Order) {
                            $invoiceable->update(['status' => \App\Enums\OrderStatusEnum::PENDING]);
                            // Dispatch notification
                            try {
                                $invoiceable->customer->notify(new \App\Notifications\OrderStatusNotification($invoiceable, 'pending'));
                            } catch (\Throwable $e) {
                                \Log::error('Order verification notification failed: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            return $this->successResponse($txn->load('invoice.invoiceable'), 'Payment verified successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to verify Razorpay payment', 500, $e->getMessage());
        }
    }

    public function initiateRazorpayOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
            ]);

            $amountInPaise = round($validated['amount'] * 100);
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
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'key_id' => $keyId,
            ], 'Razorpay order initiated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initiate Razorpay order', 500, $e->getMessage());
        }
    }



    public function initiateAdvancePayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'appointment_id' => 'required|exists:appointments,id',
                'amount' => 'required|numeric|min:1',
            ]);

            $appointment = Appointment::findOrFail($validated['appointment_id']);
            $invoice = $appointment->invoices()->first();
            if (!$invoice) {
                $invoice = app(\App\Services\InvoiceService::class)->generateForAppointment($appointment);
            }

            // Delete existing pending transactions
            $invoice->transactions()->where('status', TransactionStatusEnum::PENDING->value)->delete();

            // Create new pending transaction for the advance amount
            $txn = $invoice->transactions()->create([
                'transaction_number' => 'TXN-' . strtoupper(uniqid()),
                'payment_mode' => 'online',
                'amount' => $validated['amount'],
                'status' => TransactionStatusEnum::PENDING,
            ]);

            // Initiate Razorpay order for this transaction
            return $this->createRazorpayOrder($request, $txn->id);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initiate advance payment order: ' . $e->getMessage(), 500);
        }
    }
}