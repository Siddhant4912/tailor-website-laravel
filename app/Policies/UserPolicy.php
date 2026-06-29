<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Create a new policy instance.
     */
   public function update(User $authUser, User $targetUser)
    {
        return $authUser->role === 'ADM';
    }

    public function delete(User $authUser, User $targetUser)
    {
        return $authUser->role === 'ADM' && $authUser->id !== $targetUser->id;
    }
}
