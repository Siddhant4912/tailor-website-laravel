<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class AppointmentInvoiceController extends Controller
{
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

    public function generate(Request $request, $appointmentId)
    {
        try {
            $appointment = Appointment::findOrFail($appointmentId);

            // FIX #4: ownership check — only the customer (owner) or admin can generate
            if (
                $this->isCustomer($request) &&
                $appointment->customer_id !== $request->user()->id
            ) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Prevent duplicate
            $existing = Invoice::where('invoiceable_type', Appointment::class)
                ->where('invoiceable_id', $appointmentId)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Invoice already generated',
                    'data' => $existing
                ], 200);
            }

            $invoice = app(\App\Services\AppointmentInvoiceService::class)
                ->generate($appointmentId);

            return response()->json([
                'message' => 'Invoice generated',
                'data' => $invoice
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function download(Request $request, $id)
    {
        $invoice = Invoice::where('invoiceable_type', Appointment::class)
            ->where('id', $id)
            ->with(['invoiceable.items.garment.design', 'invoiceable.items.garment.category', 'transactions'])
            ->first();

        if (!$invoice) {
            // Find by Appointment ID
            $invoice = Invoice::where('invoiceable_type', Appointment::class)
                ->where('invoiceable_id', $id)
                ->first();

            if (!$invoice) {
                // Auto generate invoice
                $appointment = Appointment::findOrFail($id);
                $invoice = app(\App\Services\InvoiceService::class)->generateForAppointment($appointment);
            }

            // Reload relations
            $invoice->load(['invoiceable.items.garment.design', 'invoiceable.items.garment.category', 'transactions']);
        }

        // Ownership check
        $user = $request->user();
        $isAdmin = $this->isAdmin($request);

        // Safely check customer ownership of either the invoice or its parent appointment
        $invoiceCustomerId = $invoice->customer_id ?? $invoice->invoiceable?->customer_id;

        if (!$isAdmin) {
            $appointment = $invoice->invoiceable;
            $isCustomerOwner = $user->isCustomer() && $invoiceCustomerId == $user->id;
            $isStaffOwner = $user->isDeliveryStaff() && $appointment->assigned_staff_id == $user->id;
            
            if (!$isCustomerOwner && !$isStaffOwner) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $appointment = $invoice->invoiceable;

        $pdf = Pdf::loadView('invoices.appointment', [
            'invoice' => $invoice,
            'appointment' => $appointment,
        ]);

        return $pdf->download(
            'appointment-invoice-' . $invoice->invoice_number . '.pdf'
        );
    }
}
