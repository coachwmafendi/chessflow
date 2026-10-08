<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Support\Curriculum;

class LessonPolicy
{
    public function __construct(private Curriculum $curriculum) {}

    public function view(User $user, Lesson $lesson): bool
    {
        return $this->curriculum->isUnlocked($user, $lesson);
    }
}
