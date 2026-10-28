<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configure(?string $email, ?string $password, string $name = 'Direction'): void
    {
        config(['app.initial_admin' => ['email' => $email, 'password' => $password, 'name' => $name]]);
    }

    public function test_creates_a_verified_admin_when_none_exists(): void
    {
        $this->configure('direction@example.com', 'MotDePasse123');

        $this->artisan('users:ensure-admin')->assertSuccessful();

        $admin = User::where('email', 'direction@example.com')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Direction', $admin->name);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('MotDePasse123', $admin->password));
    }

    public function test_does_nothing_when_an_admin_already_exists(): void
    {
        User::factory()->admin()->create();
        $this->configure('autre@example.com', 'MotDePasse123');

        $this->artisan('users:ensure-admin')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'autre@example.com']);
    }

    public function test_does_nothing_without_credentials(): void
    {
        $this->configure(null, null);

        $this->artisan('users:ensure-admin')->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_a_short_password(): void
    {
        $this->configure('direction@example.com', 'court');

        $this->artisan('users:ensure-admin')->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_is_idempotent(): void
    {
        $this->configure('direction@example.com', 'MotDePasse123');

        $this->artisan('users:ensure-admin')->assertSuccessful();
        $this->artisan('users:ensure-admin')->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }
}
