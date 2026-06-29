<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case Draft = 'draft';
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case ASSIGNED = 'assigned';
    
    case STITCHING = 'stitching';
    case READY = 'ready';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
