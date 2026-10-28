<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoanRulesTest extends TestCase
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

    public function test_user_list_is_admin_only(): void
    {
        $this->as(null)->getJson('/api/users')->assertUnauthorized();
        $this->as(User::factory()->create())->getJson('/api/users')->assertForbidden();
        $this->as(User::factory()->create())->patchJson('/api/users/1', ['loan_limit' => 3])->assertForbidden();
    }

    public function test_admin_lists_searches_and_sees_effective_values(): void
    {
        User::factory()->create(['name' => 'Alice Martin', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob Durand', 'email' => 'bob@example.com', 'loan_limit' => 4]);
        $this->as(User::factory()->admin()->create(['name' => 'Zoé Admin']));

        $this->assertCount(3, $this->getJson('/api/users')->assertOk()->json('data'));

        $bob = $this->getJson('/api/users?search=durand')->json('data.0');
        $this->assertSame('Bob Durand', $bob['name']);
        $this->assertSame(4, $bob['loan_limit']);
        $this->assertSame(4, $bob['effective_loan_limit']);
        $this->assertNull($bob['loan_duration_days']);
        $this->assertSame(21, $bob['effective_loan_duration_days']);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        User::factory()->create(['name' => 'Alice']);
        $this->as(User::factory()->admin()->create());

        $this->assertCount(0, $this->getJson('/api/users?search=%25')->json('data'));
    }

    public function test_admin_sets_and_clears_exceptions(): void
    {
        $reader = User::factory()->create();
        $this->as(User::factory()->admin()->create())
            ->patchJson("/api/users/{$reader->id}", ['loan_limit' => 3, 'loan_duration_days' => 7])
            ->assertOk()
            ->assertJsonPath('data.effective_loan_limit', 3)
            ->assertJsonPath('data.effective_loan_duration_days', 7);

        $this->patchJson("/api/users/{$reader->id}", ['loan_limit' => null, 'loan_duration_days' => null])
            ->assertOk()
            ->assertJsonPath('data.loan_limit', null)
            ->assertJsonPath('data.effective_loan_limit', 1)
            ->assertJsonPath('data.effective_loan_duration_days', 21);
    }

    public function test_exception_values_are_validated(): void
    {
        $reader = User::factory()->create();
        $this->as(User::factory()->admin()->create());

        $this->patchJson("/api/users/{$reader->id}", ['loan_limit' => 51])->assertUnprocessable()->assertJsonValidationErrors('loan_limit');
        $this->patchJson("/api/users/{$reader->id}", ['loan_duration_days' => 0])->assertUnprocessable()->assertJsonValidationErrors('loan_duration_days');
        $this->patchJson("/api/users/{$reader->id}", ['role' => 'admin'])->assertOk();
        $this->assertSame('reader', $reader->fresh()->role);
    }

    public function test_user_exceptions_override_global_rules_when_borrowing(): void
    {
        $vip = User::factory()->create(['loan_limit' => 3, 'loan_duration_days' => 60]);
        $this->as($vip);

        $first = $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertCreated();
        $this->assertSame(60, $first->json('data.days_left'));
        $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertCreated();
        $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertCreated();
        $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertUnprocessable();

        // Les autres lecteurs gardent la règle globale (1 livre)
        $other = User::factory()->create();
        $this->as($other)->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertCreated();
        $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertUnprocessable();
    }

    public function test_user_zero_limit_means_unlimited_even_if_global_is_strict(): void
    {
        $reader = User::factory()->create(['loan_limit' => 0]);
        $this->as($reader);

        foreach (range(1, 4) as $i) {
            $this->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertCreated();
        }
    }

    public function test_renewal_uses_the_borrowers_duration(): void
    {
        $reader = User::factory()->create(['loan_duration_days' => 7]);
        $loan = Loan::factory()->active()->create(['user_id' => $reader->id, 'due_at' => now()->addDays(7)]);

        $this->as($reader)->postJson("/api/loans/{$loan->id}/renew")->assertOk()->assertJsonPath('data.days_left', 14);
    }
}
