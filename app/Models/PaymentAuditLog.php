<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAuditLog extends Model
{
    protected $fillable = [
        'customer_id',
        'staff_id',
        'loggable_type',
        'loggable_id',
        'type',
        'amount_collected',
        'amount_submitted',
        'status',
        'admin_verification_details',
    ];

    protected $casts = [
        'amount_collected' => 'decimal:2',
        'amount_submitted' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function loggable()
    {
        return $this->morphTo();
    }
}
