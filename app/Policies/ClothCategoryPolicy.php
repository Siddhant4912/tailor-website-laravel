<?php
namespace App\Policies;

use App\Models\User;
use App\Models\ClothCategory;

class ClothCategoryPolicy
{
    // Anyone can view
    public function viewAny($user = null)
    {
        return true;
    }

    public function view($user = null, ClothCategory $category)
    {
        return true;
    }

    // Only admin
    public function create(User $user)
    {
        return $user->role === 'admin';
    }

    public function update(User $user, ClothCategory $category)
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, ClothCategory $category)
    {
        return $user->role === 'admin';
    }
}