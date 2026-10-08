<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chessflow:set-role {email} {role : murid, ibubapa, guru or admin}')]
#[Description('Change the role of an account with an email (e.g. make the first admin)')]
class SetRoleCommand extends Command
{
    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));
        $user = User::firstWhere('email', (string) $this->argument('email'));

        if (! $role || ! $user) {
            $this->error(! $role ? 'Unknown role.' : 'No account with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => $role])->save();
        $this->info("{$user->email} is now {$role->value}.");

        return self::SUCCESS;
    }
}
