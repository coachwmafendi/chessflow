<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->role === Role::Admin || $classroom->teacher_id === $user->id;
    }
}
