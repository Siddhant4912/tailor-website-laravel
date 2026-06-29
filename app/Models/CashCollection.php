<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashCollection extends Model
{
    protected $fillable = [
        'staff_id',
        'customer_id',
        'collectible_type',
        'collectible_id',
        'amount_collected',
        'collected_at',
        'settlement_id',
    ];

    protected $casts = [
        'amount_collected' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function settlement()
    {
        return $this->belongsTo(CashSettlement::class, 'settlement_id');
    }

    public function collectible()
    {
        return $this->morphTo();
    }
}
