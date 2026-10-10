<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes a student account and all its data (progress, certificates, games, badges...
 * go with it through the foreign keys), for PDPA deletion requests made by the adults in charge.
 *
 * Who may delete: an admin; a parent/guardian linked to the child; a teacher only when the student
 * is in their class and nobody else (no guardian, no other teacher's class) also looks after it.
 */
class DeleteStudent
{
    /** Why `$actor` may not delete `$student`, or null when allowed. */
    public function deniedReason(User $actor, User $student): ?string
    {
        if ($student->role !== Role::Murid) {
            return 'Hanya akaun murid boleh dipadam di sini.';
        }
        if ($actor->role === Role::Admin || $actor->students()->whereKey($student->id)->exists()) {
            return null;
        }

        $teaches = $actor->taughtClassrooms()->whereHas('students', fn ($q) => $q->whereKey($student->id))->exists();
        if (! $teaches) {
            return 'Anda tiada kebenaran untuk memadam akaun ini.';
        }

        $hasGuardian = $student->guardians()->exists();
        $otherClass = $student->classrooms()->where('teacher_id', '!=', $actor->id)->exists();
        if ($hasGuardian || $otherClass) {
            return 'Murid ini juga diurus oleh ibu bapa atau guru lain. Keluarkan daripada kelas, atau minta ibu bapa atau pentadbir memadam akaun.';
        }

        return null;
    }

    public function handle(User $actor, User $student): void
    {
        if ($reason = $this->deniedReason($actor, $student)) {
            throw new AuthorizationException($reason);
        }

        DB::transaction(function () use ($student) {
            // Sign the student out everywhere; sessions have no foreign key to cascade from.
            DB::table('sessions')->where('user_id', $student->id)->delete();
            $student->delete();
        });

        // Ids only: the point of the deletion is that the name is gone.
        Log::info('Student account deleted', ['student_id' => $student->id, 'by_user_id' => $actor->id]);
    }
}
