<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simule une requête issue du SPA (domaine stateful Sanctum)
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_register_creates_reader_and_ignores_role_input(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ])->assertCreated();

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertSame('reader', $user->role);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('email', $user->email);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/user')->assertOk();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->postJson('/api/logout')->assertNoContent();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->postJson('/api/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])->assertOk();

            return true;
        });

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'newpass123'])->assertOk();
    }

    public function test_email_verification_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(config('app.frontend_url').'/email-verified');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
