<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Teachers and parents create student accounts: name only, no email (PDPA).
 * Usernames and PINs are generated and shown once to the adult.
 */
class CreateStudents
{
    public const MAX_PER_BATCH = 40;

    /**
     * @param  list<string>  $names
     * @return list<array{id: int, name: string, username: string, pin: string}>
     */
    public function handle(User $creator, array $names, ?Classroom $classroom = null): array
    {
        $names = collect($names)
            ->map(fn (string $n) => trim(preg_replace('/\s+/', ' ', $n) ?? ''))
            ->filter(fn (string $n) => $n !== '')
            ->map(fn (string $n) => mb_substr($n, 0, 60))
            ->take(self::MAX_PER_BATCH)
            ->values();

        return DB::transaction(function () use ($creator, $names, $classroom) {
            $created = [];

            foreach ($names as $name) {
                $pin = self::newPin();
                $student = new User;
                $student->forceFill([
                    'name' => $name,
                    'username' => $this->newUsername($name),
                    'role' => Role::Murid,
                    'pin' => $pin,
                    'password' => Str::random(40),
                ])->save();

                if ($classroom) {
                    $classroom->students()->attach($student->id);
                } else {
                    $creator->students()->attach($student->id);
                }

                $created[] = ['id' => $student->id, 'name' => $name, 'username' => (string) $student->username, 'pin' => $pin];
            }

            return $created;
        });
    }

    public static function newPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function newUsername(string $name): string
    {
        $base = Str::of(Str::ascii($name))->lower()->explode(' ')->first() ?? '';
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'murid';
        $base = substr($base, 0, 12);

        do {
            $username = $base.random_int(100, 999);
        } while (User::where('username', $username)->exists());

        return $username;
    }
}
