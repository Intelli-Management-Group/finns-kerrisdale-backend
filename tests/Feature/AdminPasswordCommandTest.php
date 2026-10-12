<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_command_resets_password_and_revokes_tokens(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'OldPassword123',
        ]);
        $user->createToken('existing');

        $this->artisan('admin:password', ['email' => 'ADMIN@example.com'])
            ->expectsQuestion('New password (at least 12 characters)', 'NewPassword456')
            ->expectsQuestion('Confirm new password', 'NewPassword456')
            ->expectsOutputToContain('Password reset for admin@example.com.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_password_command_does_not_change_password_when_confirmation_fails(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'OldPassword123',
        ]);

        $this->artisan('admin:password', ['email' => 'admin@example.com'])
            ->expectsQuestion('New password (at least 12 characters)', 'NewPassword456')
            ->expectsQuestion('Confirm new password', 'DifferentPassword789')
            ->expectsOutputToContain('The passwords do not match.')
            ->assertFailed();

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_command_uses_the_only_active_admin_when_default_email_is_not_found(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'actual-admin@example.com',
            'password' => 'OldPassword123',
        ]);
        $originalEmail = getenv('ADMIN_EMAIL');
        putenv('ADMIN_EMAIL=missing-admin@example.com');

        try {
            $this->artisan('admin:password')
                ->expectsQuestion('New password (at least 12 characters)', 'NewPassword456')
                ->expectsQuestion('Confirm new password', 'NewPassword456')
                ->expectsOutputToContain('using the only active admin account: actual-admin@example.com.')
                ->assertSuccessful();
        } finally {
            $originalEmail === false
                ? putenv('ADMIN_EMAIL')
                : putenv("ADMIN_EMAIL={$originalEmail}");
        }

        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
    }
}
