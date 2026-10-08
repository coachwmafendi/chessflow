<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('chessflow:create-student {username} {name} {--pin= : 4–6 digits; generated if omitted} {--guardian= : Email of the parent/teacher account to link}')]
#[Description('Create a student account (username + PIN, no email)')]
class CreateStudentCommand extends Command
{
    public function handle(): int
    {
        $pin = (string) ($this->option('pin') ?: str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT));
        $data = [
            'username' => Str::lower((string) $this->argument('username')),
            'name' => (string) $this->argument('name'),
            'pin' => $pin,
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'alpha_dash:ascii', 'min:3', 'max:30', 'unique:users,username'],
            'name' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $guardian = null;
        if ($email = $this->option('guardian')) {
            $guardian = User::firstWhere('email', $email);
            if (! $guardian) {
                $this->error("No account with email {$email}.");

                return self::FAILURE;
            }
        }

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $student = new User;
        $student->forceFill($data + [
            'role' => Role::Murid,
            'password' => Str::random(40),
        ])->save();

        $guardian?->students()->attach($student->id);

        $this->info("Student {$student->username} created. PIN: {$pin}");

        return self::SUCCESS;
    }
}
