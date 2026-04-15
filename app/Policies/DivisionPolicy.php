<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

class DivisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Division $division): bool
    {
        return $division->teacher_id === $user->id || $division->students()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Teacher');
    }

    public function update(User $user, Division $division): bool
    {
        return $division->teacher_id === $user->id;
    }

    public function delete(User $user, Division $division): bool
    {
        return $division->teacher_id === $user->id;
    }

    public function manageStudents(User $user, Division $division): bool
    {
        return $division->teacher_id === $user->id;
    }
}
