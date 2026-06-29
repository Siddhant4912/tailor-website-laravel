<?php

namespace App\Models;

use App\Enums\TransactionStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_id',
        'transaction_number',
        'payment_mode',
        'amount',
        'status',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatusEnum::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
