<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case TAILOR = 'tailor';
    case CUSTOMER = 'customer';
    case DELIVERY_STAFF = 'delivery_staff';
}
