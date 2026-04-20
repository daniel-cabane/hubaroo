<?php

namespace App\Policies;

use App\Models\KangourouSession;
use App\Models\User;

class KangourouSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, KangourouSession $kangourouSession): bool
    {
        return $kangourouSession->author_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, KangourouSession $kangourouSession): bool
    {
        return $kangourouSession->author_id === $user->id || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, KangourouSession $kangourouSession): bool
    {
        return $kangourouSession->author_id === $user->id || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, KangourouSession $kangourouSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, KangourouSession $kangourouSession): bool
    {
        return false;
    }
}
