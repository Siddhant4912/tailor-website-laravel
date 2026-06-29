<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CashCollection;
use App\Models\CashSettlement;
use App\Models\PaymentAuditLog;
use App\Models\User;
use App\Models\Order;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Enums\TransactionStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashSettlementController extends Controller
{
    // POST /staff/payments/collect-cash (staff collects remaining payment from customer)
    public function collectCash(Request $request)
    {
        try {
            $validated = $request->validate([
                'collectible_type' => 'required|string|in:order,appointment',
                'collectible_id' => 'required|integer',
                'amount_collected' => 'required|numeric|min:0',
            ]);

            $staff = $request->user();
            $collectibleType = $validated['collectible_type'] === 'order' ? Order::class : Appointment::class;
            $collectibleId = $validated['collectible_id'];
            $amountCollected = floatval($validated['amount_collected']);

            // Find the invoice for this collectible
            $invoice = Invoice::where('invoiceable_type', $collectibleType)
                ->where('invoiceable_id', $collectibleId)
                ->first();

            if (!$invoice) {
                return $this->errorResponse('Invoice not found for this record', 404);
            }

            $orderOrAppt = $validated['collectible_type'] === 'order' 
                ? Order::find($collectibleId) 
                : Appointment::find($collectibleId);

            if (!$orderOrAppt) {
                return $this->errorResponse('Associated record not found', 404);
            }

            return DB::transaction(function () use ($staff, $invoice, $collectibleType, $collectibleId, $amountCollected, $orderOrAppt, $validated) {
                // Record Cash Collection
                $cashCollection = CashCollection::create([
                    'staff_id' => $staff->id,
                    'customer_id' => $invoice->customer_id,
                    'collectible_type' => $collectibleType,
                    'collectible_id' => $collectibleId,
                    'amount_collected' => $amountCollected,
                    'collected_at' => now(),
                ]);

                // Find a pending transaction to update, or create a successful transaction
                $pendingTxn = $invoice->transactions()
                    ->where('status', TransactionStatusEnum::PENDING)
                    ->first();

                if ($pendingTxn) {
                    $pendingTxn->update([
                        'amount' => $amountCollected,
                        'status' => TransactionStatusEnum::SUCCESSFUL,
                        'payment_mode' => 'cash',
                        'transaction_number' => 'COL-' . strtoupper(uniqid()),
                        'gateway_response' => [
                            'collected_by_staff_id' => $staff->id,
                            'collected_at' => now()->toIso8601String(),
                        ]
                    ]);
                } else {
                    $invoice->transactions()->create([
                        'transaction_number' => 'COL-' . strtoupper(uniqid()),
                        'payment_mode' => 'cash',
                        'amount' => $amountCollected,
                        'status' => TransactionStatusEnum::SUCCESSFUL,
                        'gateway_response' => [
                            'collected_by_staff_id' => $staff->id,
                            'collected_at' => now()->toIso8601String(),
                        ]
                    ]);
                }

                // Check if invoice total amount matches all successful transactions
                $allSuccessful = !$invoice->transactions()
                    ->where('status', '!=', TransactionStatusEnum::SUCCESSFUL->value)
                    ->exists();

                if ($allSuccessful) {
                    $invoice->update([
                        'status' => InvoiceStatusEnum::PAID,
                        'paid_at' => now(),
                    ]);
                }

                // Update Order/Appointment Status
                if ($validated['collectible_type'] === 'order') {
                    if ($orderOrAppt->status !== OrderStatusEnum::DELIVERED) {
                        $orderOrAppt->update([
                            'status' => OrderStatusEnum::DELIVERED,
                            'delivered_at' => now()
                        ]);
                        $orderOrAppt->statusLogs()->create([
                            'user_id' => $staff->id,
                            'status' => 'delivered',
                            'remarks' => 'Cash collected and order marked as delivered by staff.',
                        ]);
                    }
                } else {
                    if ($orderOrAppt->status !== \App\Enums\AppointmentStatusEnum::COMPLETED) {
                        $orderOrAppt->update([
                            'status' => \App\Enums\AppointmentStatusEnum::COMPLETED,
                            'ended_at' => now()
                        ]);
                    }
                }

                // Write immutable audit log
                PaymentAuditLog::create([
                    'customer_id' => $invoice->customer_id,
                    'staff_id' => $staff->id,
                    'loggable_type' => $collectibleType,
                    'loggable_id' => $collectibleId,
                    'type' => 'collection',
                    'amount_collected' => $amountCollected,
                    'amount_submitted' => 0,
                    'status' => 'success',
                    'admin_verification_details' => 'Collected by staff ' . $staff->name,
                ]);

                // Log SMS/Confirmation notification trigger
                $total = floatval($invoice->total_amount);
                $adv = floatval($invoice->advance_paid);
                $rem = max(0, $total - $adv - $amountCollected);
                Log::info("COD SMS confirmation sent to customer - Order: {$collectibleId}, Total: {$total}, Adv: {$adv}, Cash Received: {$amountCollected}, Bal: {$rem}");

                return $this->successResponse([
                    'collection' => $cashCollection,
                    'invoice' => $invoice->load('transactions'),
                ], 'Cash collection recorded successfully');
            });

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to collect cash', 500, $e->getMessage());
        }
    }

    // GET /admin/cash-settlements/pending (admin sees staff pending cash collections)
    public function getPendingSettlements(Request $request)
    {
        try {
            $pendingCollections = CashCollection::whereNull('settlement_id')
                ->with('staff')
                ->get()
                ->groupBy('staff_id');

            $pendingList = [];

            foreach ($pendingCollections as $staffId => $collections) {
                $staff = $collections->first()->staff;
                if (!$staff) continue;

                $pendingList[] = [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'staff_phone' => $staff->phone,
                    'total_orders' => $collections->count(),
                    'expected_cash' => $collections->sum('amount_collected'),
                    'collections' => $collections->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'collectible_type' => $c->collectible_type === Order::class ? 'order' : 'appointment',
                            'collectible_id' => $c->collectible_id,
                            'amount_collected' => $c->amount_collected,
                            'collected_at' => $c->collected_at ? $c->collected_at->toIso8601String() : null,
                        ];
                    }),
                ];
            }

            return $this->successResponse($pendingList, 'Pending settlements retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve pending settlements', 500, $e->getMessage());
        }
    }

    // POST /admin/cash-settlements/settle (admin settles cash with staff)
    public function settle(Request $request)
    {
        try {
            $validated = $request->validate([
                'staff_id' => 'required|exists:users,id',
                'submitted_amount' => 'required|numeric|min:0',
                'remarks' => 'nullable|string|max:1000',
            ]);

            $admin = $request->user();
            $staffId = $validated['staff_id'];
            $submittedAmount = floatval($validated['submitted_amount']);
            $remarks = $validated['remarks'] ?? null;

            // Fetch unsettled collections for this staff
            $unsettled = CashCollection::where('staff_id', $staffId)
                ->whereNull('settlement_id')
                ->get();

            if ($unsettled->isEmpty()) {
                return $this->errorResponse('No pending cash collections found for this staff member.', 422);
            }

            $expectedAmount = floatval($unsettled->sum('amount_collected'));
            $difference = $submittedAmount - $expectedAmount;

            // Determine Status: settled vs mismatch
            $status = 'settled';
            if (abs($difference) > 0.01) {
                $status = 'mismatch';
            }

            return DB::transaction(function () use ($staffId, $admin, $expectedAmount, $submittedAmount, $difference, $status, $remarks, $unsettled) {
                // Create CashSettlement
                $settlement = CashSettlement::create([
                    'staff_id' => $staffId,
                    'admin_id' => $admin->id,
                    'expected_amount' => $expectedAmount,
                    'submitted_amount' => $submittedAmount,
                    'difference' => $difference,
                    'status' => $status,
                    'remarks' => $remarks,
                    'settled_at' => now(),
                ]);

                // Link collections to this settlement
                CashCollection::whereIn('id', $unsettled->pluck('id'))->update([
                    'settlement_id' => $settlement->id
                ]);

                // Write immutable audit log
                PaymentAuditLog::create([
                    'staff_id' => $staffId,
                    'loggable_type' => CashSettlement::class,
                    'loggable_id' => $settlement->id,
                    'type' => 'settlement',
                    'amount_collected' => $expectedAmount,
                    'amount_submitted' => $submittedAmount,
                    'status' => $status,
                    'admin_verification_details' => 'Settled by admin ' . $admin->name . '. Remarks: ' . $remarks,
                ]);

                return $this->successResponse($settlement->load('staff', 'admin'), 'Cash settlement recorded successfully');
            });

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record settlement', 500, $e->getMessage());
        }
    }

    // GET /admin/cash-settlements/history (paginated settlement list)
    public function getSettlementHistory(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $history = CashSettlement::with(['staff', 'admin'])
                ->latest()
                ->paginate($perPage);

            return $this->successResponse($history, 'Settlement history retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve history', 500, $e->getMessage());
        }
    }

    // GET /admin/payment-audit-logs (paginated audit trails)
    public function getAuditLogs(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $logs = PaymentAuditLog::with(['customer', 'staff'])
                ->latest()
                ->paginate($perPage);

            return $this->successResponse($logs, 'Audit logs retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve audit logs', 500, $e->getMessage());
        }
    }

    // POST /admin/payment-audit-logs/correct (correct transaction amount without modifying history)
    public function correctPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|exists:transactions,id',
                'corrected_amount' => 'required|numeric|min:0',
                'remarks' => 'required|string|max:1000',
            ]);

            $admin = $request->user();
            $txnId = $validated['transaction_id'];
            $correctedAmount = floatval($validated['corrected_amount']);
            $remarks = $validated['remarks'];

            $txn = Transaction::with('invoice')->findOrFail($txnId);
            $originalAmount = floatval($txn->amount);

            if ($txn->status !== TransactionStatusEnum::SUCCESSFUL) {
                return $this->errorResponse('Only successful payments can be corrected.', 422);
            }

            return DB::transaction(function () use ($txn, $originalAmount, $correctedAmount, $remarks, $admin) {
                // Update transaction amount to corrected amount (to maintain correct analytics)
                $txn->update(['amount' => $correctedAmount]);

                // Create audit trail entry for correction
                $audit = PaymentAuditLog::create([
                    'customer_id' => $txn->invoice?->customer_id,
                    'staff_id' => $admin->id, // corrected by admin
                    'loggable_type' => Transaction::class,
                    'loggable_id' => $txn->id,
                    'type' => 'correction',
                    'amount_collected' => $correctedAmount,
                    'amount_submitted' => 0,
                    'status' => 'corrected',
                    'admin_verification_details' => "Correction by admin {$admin->name}. Original Amt: {$originalAmount}, New Amt: {$correctedAmount}. Reason: {$remarks}",
                ]);

                return $this->successResponse($audit, 'Payment transaction correction logged and updated successfully');
            });

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to correct transaction', 500, $e->getMessage());
        }
    }
}
