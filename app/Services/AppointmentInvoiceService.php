<?php

namespace App\Services;

use App\Models\Appointment;
use App\Services\InvoiceService;

class AppointmentInvoiceService
{
    protected $invoiceService;
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Generate invoice for an appointment
     */
    public function generate($appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        return $this->invoiceService->generateForAppointment($appointment);
    }
}