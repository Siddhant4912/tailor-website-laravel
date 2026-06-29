<?php

namespace App\Models;

use App\Enums\AppointmentStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'customer_id',
        'assigned_staff_id',
        'type',
        'gender',                   // NEW: 'male', 'female'
        'female_tailor_visit',      // NEW: 'yes', 'no'
        'has_fabric',               // NEW: 'yes', 'no'
        'measurement_type',         // NEW: 'onsite_visit', 'existing_garment'
        'appointment_date',
        'appointment_time',
        'address_line',
        'city',
        'state',
        'pincode',
        'status',
        'visit_charge',
        'deposit_amount',           // NEW: stitching holding deposit (e.g. 100.00)
        'cloth_advance_amount',     // NEW: fabric advance (e.g. 5%)
        'cloth_total_amount',       // NEW: total cost of fabric
        'payment_status',
        'started_at',
        'ended_at',
        'is_visited',
        'measurement_taken',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatusEnum::class,
            // 'payment_status' => PaymentStatusEnum::class,
            'appointment_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_visited' => 'boolean',
            'measurement_taken' => 'boolean',
            'visit_charge' => 'decimal:2',
            'deposit_amount' => 'decimal:2',       // NEW
            'cloth_advance_amount' => 'decimal:2', // NEW
            'cloth_total_amount' => 'decimal:2',   // NEW
        ];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function items()
    {
        return $this->hasMany(AppointmentItem::class);
    }

    public function uploads()
    {
        return $this->hasMany(AppointmentUpload::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function statusLogs()
    {
        return $this->morphMany(StatusLog::class, 'loggable');
    }
}
