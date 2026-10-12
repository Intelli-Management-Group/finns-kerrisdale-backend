<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminPassword extends Command
{
    protected $signature = 'admin:password {email? : Email address of the admin account}';

    protected $description = 'Reset an admin account password';

    public function handle(): int
    {
        $providedEmail = $this->argument('email');
        $email = strtolower(trim((string) ($providedEmail ?: env('ADMIN_EMAIL', 'admin@finnskerrisdaleladies.com'))));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid admin email address is required.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)
            ->where('role', User::ROLE_ADMIN)
            ->first();

        if (! $user && ! $providedEmail) {
            $admins = User::where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->orderBy('email')
                ->get();

            if ($admins->count() === 1) {
                $user = $admins->first();
                $email = $user->email;
                $this->warn("The configured admin email was not found; using the only active admin account: {$email}.");
            } elseif ($admins->isNotEmpty()) {
                $email = $this->choice(
                    'The configured admin email was not found. Select an active admin account:',
                    $admins->pluck('email')->all()
                );
                $user = $admins->firstWhere('email', $email);
            }
        }

        if (! $user) {
            $this->error("No active admin account was found for {$email}.");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error("The admin account for {$email} is disabled.");

            return self::FAILURE;
        }

        $password = $this->secret('New password (at least 12 characters)');
        if (! is_string($password) || Str::length($password) < 12) {
            $this->error('The password must be at least 12 characters long.');

            return self::FAILURE;
        }

        $confirmation = $this->secret('Confirm new password');
        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();
        $revokedTokens = $user->tokens()->delete();

        $this->info("Password reset for {$email}. Revoked {$revokedTokens} API token(s).");

        return self::SUCCESS;
    }
}
