<?php

namespace App\Enums;

enum InvoiceStatusEnum: string
{
    case GENERATED = 'generated';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
