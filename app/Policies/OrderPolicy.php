<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Usually anyone authenticated can view 'their' orders, filtered in controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            return $user->id === $order->customer_id;
        }

        if ($user->isDeliveryStaff()) {
            return $user->id === $order->delivery_staff_id;
        }

        return false; // Tailors view items, not the whole order
    }

    /**
     * Determine whether the user can update the model's status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin() || $user->isDeliveryStaff();
    }
}
