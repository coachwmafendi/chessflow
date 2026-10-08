<?php

namespace App\Actions;

use App\Models\User;

class ResetStudentPin
{
    public function handle(User $student): string
    {
        $pin = CreateStudents::newPin();
        $student->forceFill(['pin' => $pin])->save();

        return $pin;
    }
}
