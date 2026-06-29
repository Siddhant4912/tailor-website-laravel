<?php

namespace App\Models;

use App\Enums\InvoiceStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected static function booted()
    {
        static::updated(function ($invoice) {
            if ($invoice->wasChanged('status') && $invoice->status === InvoiceStatusEnum::PAID) {
                if ($invoice->invoiceable_type === 'App\Models\Appointment') {
                    $invoice->invoiceable()->update(['payment_status' => 'paid']);
                }
            }
        });
    }

    protected $fillable = [
        'customer_id',
        'invoiceable_id',
        'invoiceable_type',
        'invoice_number',
        'subtotal',
        'visit_charge',
        'advance_paid',
        'gst_rate',
        'gst_amount',
        'total_amount',
        'status',
        'generated_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatusEnum::class,
            'generated_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'visit_charge' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
