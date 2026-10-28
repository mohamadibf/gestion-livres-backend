<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->travelTo(now()->setTime(10, 0));
    }

    private function as(?User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $user ? $this->actingAs($user, 'web') : $this;
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->as(null)->patchJson('/api/profile', [])->assertUnauthorized();
        $this->putJson('/api/profile/password', [])->assertUnauthorized();
        $this->deleteJson('/api/profile')->assertUnauthorized();
        $this->getJson('/api/profile/stats')->assertUnauthorized();
    }

    public function test_name_can_change_without_password(): void
    {
        $user = User::factory()->create(['name' => 'Ancien', 'email' => 'a@example.com']);
        Notification::fake();

        $this->as($user)->patchJson('/api/profile', ['name' => 'Nouveau', 'email' => 'a@example.com'])
            ->assertOk()->assertJsonPath('name', 'Nouveau');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->as($user)->patchJson('/api/profile', ['name' => 'X', 'email' => 'b@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->patchJson('/api/profile', ['name' => 'X', 'email' => 'b@example.com', 'current_password' => 'mauvais'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertSame('a@example.com', $user->fresh()->email);
    }

    public function test_email_change_unverifies_account_and_sends_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->as($user)->patchJson('/api/profile', ['name' => $user->name, 'email' => 'b@example.com', 'current_password' => 'password'])
            ->assertOk()->assertJsonPath('email', 'b@example.com')->assertJsonPath('email_verified_at', null);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);

        // Compte non vérifié : plus d'emprunt tant que l'adresse n'est pas confirmée
        $book = Book::factory()->create();
        $this->postJson("/api/books/{$book->id}/borrow")->assertForbidden();
    }

    public function test_email_must_be_unique_and_valid(): void
    {
        User::factory()->create(['email' => 'pris@example.com']);
        $user = User::factory()->create();

        $this->as($user)->patchJson('/api/profile', ['name' => 'X', 'email' => 'pris@example.com', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/profile', ['name' => 'X', 'email' => 'pas-un-email', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/profile', ['name' => '', 'email' => $user->email])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_user_cannot_change_role_through_profile(): void
    {
        $user = User::factory()->create();

        $this->as($user)->patchJson('/api/profile', ['name' => 'X', 'email' => $user->email, 'role' => 'admin', 'loan_limit' => 99])->assertOk();

        $this->assertSame('reader', $user->fresh()->role);
        $this->assertNull($user->fresh()->loan_limit);
    }

    public function test_password_change(): void
    {
        $user = User::factory()->create();
        $this->as($user);

        $this->putJson('/api/profile/password', ['current_password' => 'faux', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'court1', 'password_confirmation' => 'court1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'Nouveau123', 'password_confirmation' => 'autre'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'Nouveau123', 'password_confirmation' => 'Nouveau123'])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Nouveau123', $user->fresh()->password));
    }

    public function test_delete_requires_password(): void
    {
        $user = User::factory()->create();

        $this->as($user)->deleteJson('/api/profile')->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->deleteJson('/api/profile', ['current_password' => 'faux'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_is_blocked_while_a_book_is_borrowed(): void
    {
        $user = User::factory()->create();
        Loan::factory()->active()->create(['user_id' => $user->id]);

        $this->as($user)->deleteJson('/api/profile', ['current_password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('message', 'Rendez votre livre emprunté avant de supprimer votre compte.');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_last_admin_cannot_delete_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->as($admin)->deleteJson('/api/profile', ['current_password' => 'password'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        User::factory()->admin()->create();
        $this->as($admin)->deleteJson('/api/profile', ['current_password' => 'password'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }

    public function test_delete_removes_account_and_loan_history(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->returned()->create(['user_id' => $user->id]);

        $this->as($user)->deleteJson('/api/profile', ['current_password' => 'password'])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
        // Le livre, lui, est conservé
        $this->assertDatabaseHas('books', ['id' => $loan->book_id]);
    }

    public function test_stats(): void
    {
        $user = User::factory()->create(['loan_limit' => 3]);
        Loan::factory()->returned()->create(['user_id' => $user->id]);                                   // rendu à temps
        Loan::factory()->returned()->create(['user_id' => $user->id, 'due_at' => now()->subDays(5)]);     // rendu en retard
        $current = Loan::factory()->active()->create(['user_id' => $user->id, 'due_at' => now()->subDays(2)]); // en retard
        Loan::factory()->active()->create(); // autre lecteur : ignoré

        $data = $this->as($user)->getJson('/api/profile/stats')->assertOk()->json('data');

        $this->assertSame(3, $data['total_loans']);
        $this->assertSame(2, $data['returned_loans']);
        $this->assertSame(1, $data['active_count']);
        $this->assertSame(2, $data['late_loans']);
        $this->assertSame(3, $data['loan_limit']);
        $this->assertSame(21, $data['loan_duration_days']);
        $this->assertSame($current->id, $data['active_loan']['id']);
        $this->assertTrue($data['active_loan']['overdue']);
        $this->assertSame($current->book->title, $data['active_loan']['book']['title']);
    }

    public function test_stats_without_loan(): void
    {
        $this->as(User::factory()->create())->getJson('/api/profile/stats')
            ->assertOk()->assertJsonPath('data.active_loan', null)->assertJsonPath('data.total_loans', 0);
    }
}
