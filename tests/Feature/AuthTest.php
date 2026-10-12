<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        User::factory()->admin()->create(['email' => 'robin@example.com', 'password' => 'Secret12345']);

        $res = $this->postJson('/api/auth/login', ['email' => 'Robin@Example.com', 'password' => 'Secret12345'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'email', 'role']]]);

        $token = $res->json('data.token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'robin@example.com');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'nope'])
            ->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'password' => 'Secret12345', 'is_active' => false]);
        $this->postJson('/api/auth/login', ['email' => 'off@example.com', 'password' => 'Secret12345'])->assertStatus(403);
    }

    public function test_login_validation_uses_envelope(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Validation failed'])
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'bad']);
        }
        $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_admin_api_requires_authentication(): void
    {
        $this->getJson('/api/admin/posts')->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_editor_cannot_reach_admin_only_areas(): void
    {
        $this->actingAsEditor();
        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/settings')->assertStatus(403);
        $this->getJson('/api/admin/redirects')->assertStatus(403);
        $this->getJson('/api/admin/posts')->assertOk(); // but can manage content
    }
}
