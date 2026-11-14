<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'ballpit:make-admin
                            {email : The account email address}
                            {--name= : Display name, for a new account}
                            {--password= : Password, for a new account (generated when omitted)}';

    protected $description = 'Grant admin access to a user, creating the account if needed';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->promote($user);

            $this->components->info("{$email} now has admin access.");

            return self::SUCCESS;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));

        $user = User::create([
            'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->promote($user);

        $this->components->info("Created an admin account for {$email}.");

        if (! $this->option('password')) {
            $this->components->warn("Temporary password: {$password}");
            $this->components->warn('Store it somewhere safe — it will not be shown again.');
        }

        return self::SUCCESS;
    }

    /**
     * `is_admin` is intentionally not mass-assignable, so it is set directly
     * rather than through update().
     */
    protected function promote(User $user): void
    {
        $user->is_admin = true;
        $user->save();
    }
}
