<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSettlement extends Model
{
    protected $fillable = [
        'staff_id',
        'admin_id',
        'expected_amount',
        'submitted_amount',
        'difference',
        'status',
        'remarks',
        'settled_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'submitted_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function collections()
    {
        return $this->hasMany(CashCollection::class, 'settlement_id');
    }
}
