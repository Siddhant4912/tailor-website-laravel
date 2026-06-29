<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'appointment_id',
        'customer_id',
        'pickup_staff_id',
        'delivery_staff_id',
        'subtotal',
        'gst_rate',
        'gst_amount',
        'visit_charge',
        'advance_paid',
        'total_price',
        'delivery_date',
        'status',
        'pickup_at',
        'delivered_at',
        'delivery_address',
        'notes',
        'delivery_proof',
        'delivery_otp',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatusEnum::class,
            'delivery_date' => 'date',
            'pickup_at' => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'visit_charge' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function pickupStaff()
    {
        return $this->belongsTo(User::class, 'pickup_staff_id');
    }

    public function deliveryStaff()
    {
        return $this->belongsTo(User::class, 'delivery_staff_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function measurements()
    {
        return $this->hasMany(OrderMeasurement::class);
    }

    public function invoices()
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function statusLogs()
    {
        return $this->morphMany(StatusLog::class, 'loggable');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}