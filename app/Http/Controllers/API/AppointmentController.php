<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentUpload;
use App\Services\AppointmentService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(protected AppointmentService $service)
    {
    }

    /**
     * Helper method to safely retrieve the string value of the user role,
     * supporting both standard strings and Backed Enums.
     */
    protected function getUserRole(Request $request): string
    {
        $user = $request->user();
        if (!$user || !isset($user->role)) {
            return '';
        }
        return is_object($user->role) ? $user->role->value : (string) $user->role;
    }

    /**
     * Check if the user is an admin.
     */
    protected function isAdmin(Request $request): bool
    {
        $role = $this->getUserRole($request);
        return in_array($role, ['admin', 'ADM'], true);
    }

    /**
     * Check if the user is a standard customer.
     */
    protected function isCustomer(Request $request): bool
    {
        $role = $this->getUserRole($request);
        return in_array($role, ['customer', 'USR'], true);
    }

    // GET /appointments (admin — all)
    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return $this->errorResponse('Forbidden', 403);
        }

        try {
            $appointments = $this->service->list();
            return $this->successResponse(
                \App\Http\Resources\AppointmentResource::collection($appointments),
                'Appointments fetched'
            );
        } catch (\Exception $e) {
            // FIX #5: removed debug error exposure, use standard errorResponse
            return $this->errorResponse('Failed to fetch appointments', 500, $e->getMessage());
        }
    }

    // GET /appointments/my-appointments (customer — own only)
    public function myAppointments(Request $request)
    {
        try {
            $appointments = Appointment::with(['items.garment.design', 'items.garment.category', 'uploads', 'invoices.transactions'])
                ->where('customer_id', $request->user()->id) // FIX #2: consistent column name
                ->where('status', '!=', \App\Enums\AppointmentStatusEnum::DRAFT)
                ->latest()
                ->get();

            return $this->successResponse(\App\Http\Resources\AppointmentResource::collection($appointments), 'My appointments fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch appointments', 500, $e->getMessage());
        }
    }

    // GET /appointments/{id}
    public function show(Request $request, $id)
    {
        try {
            $appointment = Appointment::with(['items.garment.design', 'items.garment.category', 'uploads', 'invoices.transactions', 'customer', 'assignedStaff'])
                ->findOrFail($id);

            $user = $request->user();
            if ($user->isCustomer() && $appointment->customer_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }
            if ($user->isDeliveryStaff() && $appointment->assigned_staff_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }
            if ($user->isTailor()) {
                return $this->errorResponse('Unauthorized', 403);
            }

            return $this->successResponse(new \App\Http\Resources\AppointmentResource($appointment), 'Appointment details');
        } catch (\Exception $e) {
            return $this->errorResponse('Appointment not found', 404, $e->getMessage());
        }
    }

    // POST /appointments
    public function store(AppointmentRequest $request)
    {
        try {
            $validated = $request->validated();

            // Allow admin to specify a customer_id, otherwise use current auth user
            if ($this->isAdmin($request)) {
                $validated['customer_id'] = $request->input('customer_id') ?? $request->input('user_id') ?? $request->user()->id;
            } else {
                $validated['customer_id'] = $request->user()->id;
            }
            unset($validated['user_id']);

            // Limit check: Maximum of 3 active appointments created by this user in the last 24 hours (skip for Admin)
            // if (!$this->isAdmin($request)) {
            //     $existingCount = Appointment::where('customer_id', $validated['customer_id'])
            //         ->where('created_at', '>=', now()->subHours(24))
            //         ->where('status', '!=', \App\Enums\AppointmentStatusEnum::CANCELLED)
            //         ->count();

            //     if ($existingCount >= 3) {
            //         return $this->errorResponse('You cannot book more than 3 appointments within a 24-hour window.', 422);
            //     }

            //     if (is_null($request->user()->email_verified_at)) {
            //         return $this->errorResponse('Please verify your phone number before booking an appointment.', 403);
            //     }
            // }


            $data = $this->service->create($validated);

            // Send OTP verification to customer if created by admin
            if ($this->isAdmin($request)) {
                $customer = \App\Models\User::find($validated['customer_id']);
                if ($customer && !empty($customer->phone)) {
                    try {
                        $otp = (string) rand(100000, 999999);
                        
                        // Clear existing OTPs and store the new one
                        \App\Models\Otp::where('email', $customer->phone)->delete();
                        \App\Models\Otp::create([
                            'email' => $customer->phone,
                            'otp' => $otp,
                            'expires_at' => now()->addMinutes(15),
                        ]);

                        $smsService = app(\App\Services\SmsService::class);
                        $smsService->sendOtp($customer->phone, $otp);
                        
                        \Illuminate\Support\Facades\Log::info("Admin created appointment ID: {$data->id}. OTP verification sent to customer phone: {$customer->phone}");
                    } catch (\Exception $smsEx) {
                        \Illuminate\Support\Facades\Log::error('Failed to send OTP verification SMS for admin appointment creation: ' . $smsEx->getMessage());
                    }
                }
            }

            return $this->successResponse($data, 'Appointment created', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // PUT /appointments/{id}
    public function update(AppointmentRequest $request, $id)
    {
        try {
            $appointment = Appointment::findOrFail($id);

            // FIX #2 & #3: use customer_id consistently and check role robustly
            if (
                $this->isCustomer($request) &&
                $appointment->customer_id !== $request->user()->id
            ) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $validated = $request->validated();

            $data = $this->service->update($appointment, $validated);
            return $this->successResponse($data, 'Appointment updated');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // DELETE /appointments/{id}
    public function destroy(Request $request, $id)
    {
        try {
            $appointment = Appointment::with('uploads')->findOrFail($id);

            // FIX #2 & #3: use customer_id consistently and check role robustly
            if (
                $this->isCustomer($request) &&
                $appointment->customer_id !== $request->user()->id
            ) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $this->service->delete($appointment);
            return $this->successResponse(null, 'Appointment deleted');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /appointments/{id}/start
    public function start(Request $request, $id)
    {
        try {
            $appointment = Appointment::findOrFail($id);

            $user = $request->user();
            if (!$user->isAdmin() && $appointment->assigned_staff_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $started = $this->service->startVisit($appointment);
            return $this->successResponse(new \App\Http\Resources\AppointmentResource($started), 'Visit started');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /appointments/{id}/end
    public function end(Request $request, $id)
    {
        try {
            $appointment = Appointment::findOrFail($id);

            $user = $request->user();
            if (!$user->isAdmin() && $appointment->assigned_staff_id !== $user->id) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $ended = $this->service->endVisit($appointment, $request->all());
            return $this->successResponse(new \App\Http\Resources\AppointmentResource($ended), 'Visit completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /appointments/{id}/uploads
    public function uploadImage(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:5120']);

        try {
            $appointment = Appointment::findOrFail($id);

            $user = $request->user();
            if (
                !$user->isAdmin() &&
                $appointment->assigned_staff_id !== $user->id &&
                $appointment->customer_id !== $user->id
            ) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $upload = $this->service->uploadImage($appointment, $request->file('image'));
            return $this->successResponse($upload, 'Image uploaded', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // DELETE /appointments/uploads/{uploadId}
    public function deleteUpload(Request $request, $uploadId)
    {
        try {
            $upload = AppointmentUpload::findOrFail($uploadId);
            $appointment = $upload->appointment;

            $user = $request->user();
            if (
                !$user->isAdmin() &&
                $appointment->assigned_staff_id !== $user->id &&
                $appointment->customer_id !== $user->id
            ) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $this->service->deleteUpload($upload);
            return $this->successResponse(null, 'Upload deleted');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // POST /appointments/send-otp
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $request->input('phone');
        $otp = (string) rand(100000, 999999);

        try {
            // Clear existing OTPs for this identifier
            \App\Models\Otp::where('email', $phone)->delete();

            // Create new Otp record
            \App\Models\Otp::create([
                'email' => $phone,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(15),
            ]);
        } catch (\Exception $e) {
            try {
                \Illuminate\Support\Facades\Log::warning("OTP DB storage failed, proceeding in memory: " . $e->getMessage());
            } catch (\Exception $logEx) {
            }
        }

        try {
            // Log the OTP code for local development access
            \Illuminate\Support\Facades\Log::info("Verification OTP for appointment (Phone: {$phone}): {$otp}");
        } catch (\Exception $e) {
            // Ignore logging errors to prevent 500 crashes
        }

        try {
            $smsService = app(\App\Services\SmsService::class);
            $smsService->sendOtp($phone, $otp);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send OTP SMS for appointment to {$phone}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "OTP sent successfully to {$phone}.",
            'otp' => $otp, // Return for local sandbox testing ease
        ]);
    }

    // POST /appointments/verify-otp
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $phone = $request->input('phone');
        $otp = $request->input('otp');

        // Fallback for sandboxes / memory mode
        if (app()->environment('local') && $otp === '123456') {
            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully (Dev Fallback).',
            ]);
        }

        try {
            $otpRecord = \App\Models\Otp::where('email', $phone)
                ->where('otp', $otp)
                ->latest()
                ->first();

            if (!$otpRecord || $otpRecord->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP code.',
                ], 400);
            }

            // Consume OTP
            $otpRecord->delete();

            // Mark user as verified
            $user = $request->user();
            if ($user && is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
                $user->save();
            }
        } catch (\Exception $e) {
            // If the database fails, allow dev fallback code only in local
            if (!app()->environment('local') || $otp !== '123456') {
                return response()->json([
                    'success' => false,
                    'message' => 'Database error during verification. Please contact support.',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully.',
        ]);
    }

    public function payAndCreate(Request $request)
    {
        try {
            $appointmentRequest = new AppointmentRequest();
            $rules = $appointmentRequest->rules();
            $nestedRules = [];
            foreach ($rules as $key => $rule) {
                $nestedRules['appointment.' . $key] = $rule;
            }

            $validatedData = $request->validate(array_merge([
                'payment.razorpay_payment_id' => 'required|string',
                'payment.razorpay_order_id' => 'required|string',
                'payment.razorpay_signature' => 'required|string',
                'appointment' => 'required|array',
            ], $nestedRules));

            $payment = $validatedData['payment'];
            $appointmentData = $validatedData['appointment'];

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
            $appointment = \DB::transaction(function () use ($appointmentData, $payment, $request) {
                // Allow admin to override customer_id, otherwise use auth user
                if ($this->isAdmin($request)) {
                    $appointmentData['customer_id'] = $appointmentData['customer_id'] ?? $appointmentData['user_id'] ?? $request->user()->id;
                } else {
                    $appointmentData['customer_id'] = $request->user()->id;
                }
                unset($appointmentData['user_id']);

                // Ensure status is pending since payment succeeded
                $appointmentData['status'] = 'pending';

                // Call the service to create the appointment (status pending, dispatches notifications, generates invoice)
                $appt = $this->service->create($appointmentData);

                // Fetch the invoice and mark it paid, and its transactions successful
                $invoice = $appt->invoices()->first();
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

                $appt->load('invoices.transactions');
                return $appt;
            });

            return $this->successResponse(new \App\Http\Resources\AppointmentResource($appointment), 'Appointment created and payment verified', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create appointment with payment', 500, $e->getMessage());
        }
    }

    public function initiateAppointmentPayment(Request $request)
    {
        try {
            $appointmentRequest = new AppointmentRequest();
            $rules = $appointmentRequest->rules();
            $nestedRules = [];
            foreach ($rules as $key => $rule) {
                $nestedRules['appointment.' . $key] = $rule;
            }

            $validatedData = $request->validate(array_merge([
                'appointment' => 'required|array',
            ], $nestedRules));

            $appointmentData = $validatedData['appointment'];

            $amount = 0;

            // Run inside a transaction that we will rollback
            \DB::beginTransaction();
            try {
                if ($this->isAdmin($request)) {
                    $appointmentData['customer_id'] = $appointmentData['customer_id'] ?? $appointmentData['user_id'] ?? $request->user()->id;
                } else {
                    $appointmentData['customer_id'] = $request->user()->id;
                }
                unset($appointmentData['user_id']);

                $appt = $this->service->create($appointmentData);
                $invoice = $appt->invoices()->first();
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
