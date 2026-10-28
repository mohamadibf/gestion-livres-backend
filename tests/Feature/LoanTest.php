<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->travelTo(now()->setTime(10, 0));
    }

    /** Change d'utilisateur : le garde Sanctum garde sinon en mémoire le premier utilisateur du test. */
    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function borrow(User $user, Book $book)
    {
        return $this->as($user)->postJson("/api/books/{$book->id}/borrow");
    }

    public function test_reader_borrows_an_available_book_for_21_days(): void
    {
        $reader = User::factory()->create();
        $book = Book::factory()->create();

        $this->borrow($reader, $book)
            ->assertCreated()
            ->assertJsonPath('data.mine', true)
            ->assertJsonPath('data.days_left', 21)
            ->assertJsonPath('data.can_renew', true);

        $this->assertDatabaseHas('loans', ['book_id' => $book->id, 'user_id' => $reader->id, 'active_book_id' => $book->id]);
    }

    public function test_cannot_borrow_onsite_only_or_already_borrowed_book(): void
    {
        $reader = User::factory()->create();

        $this->borrow($reader, Book::factory()->create(['loanable' => false]))
            ->assertUnprocessable()->assertJsonPath('message', 'Ce livre est réservé à la consultation sur place.');

        $taken = Book::factory()->create();
        Loan::factory()->active()->create(['book_id' => $taken->id]);
        $this->borrow($reader, $taken)
            ->assertUnprocessable()->assertJsonPath('message', 'Ce livre est déjà emprunté.');
    }

    public function test_reader_is_limited_to_one_active_loan(): void
    {
        $reader = User::factory()->create();
        $this->borrow($reader, Book::factory()->create())->assertCreated();

        $this->borrow($reader, Book::factory()->create())
            ->assertUnprocessable()
            ->assertJsonPath('message', "Vous avez déjà un livre emprunté. Rendez-le avant d'en emprunter un autre.");
    }

    public function test_limit_is_configurable_and_zero_means_unlimited(): void
    {
        config(['loans.max_active_per_user' => 2]);
        $reader = User::factory()->create();
        $this->borrow($reader, Book::factory()->create())->assertCreated();
        $this->borrow($reader, Book::factory()->create())->assertCreated();
        $this->borrow($reader, Book::factory()->create())->assertUnprocessable();

        config(['loans.max_active_per_user' => 0]);
        $this->borrow($reader, Book::factory()->create())->assertCreated();
    }

    public function test_unverified_user_cannot_borrow(): void
    {
        $this->borrow(User::factory()->unverified()->create(), Book::factory()->create())->assertForbidden();
    }

    public function test_return_by_owner_frees_the_book_and_the_reader(): void
    {
        $reader = User::factory()->create();
        $book = Book::factory()->create();
        $loanId = $this->borrow($reader, $book)->json('data.id');

        $this->postJson("/api/loans/{$loanId}/return")->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('loans', ['id' => $loanId, 'active_book_id' => null]);

        $this->postJson("/api/loans/{$loanId}/return")->assertUnprocessable()->assertJsonPath('message', 'Ce livre a déjà été rendu.');

        // Le livre et le lecteur sont de nouveau libres
        $this->borrow($reader, $book)->assertCreated();
    }

    public function test_only_owner_or_admin_can_return(): void
    {
        $owner = User::factory()->create();
        $loanId = $this->borrow($owner, Book::factory()->create())->json('data.id');

        $this->as(User::factory()->create())->postJson("/api/loans/{$loanId}/return")->assertForbidden();
        $this->as(User::factory()->admin()->create())->postJson("/api/loans/{$loanId}/return")->assertOk();
    }

    public function test_renewal_once_then_refused(): void
    {
        $reader = User::factory()->create();
        $loanId = $this->borrow($reader, Book::factory()->create())->json('data.id');

        $this->postJson("/api/loans/{$loanId}/renew")
            ->assertOk()
            ->assertJsonPath('data.days_left', 42)
            ->assertJsonPath('data.renewals_count', 1)
            ->assertJsonPath('data.can_renew', false);

        $this->postJson("/api/loans/{$loanId}/renew")->assertUnprocessable()->assertJsonPath('message', 'Cet emprunt a déjà été prolongé.');
    }

    public function test_overdue_loan_cannot_be_renewed_and_is_flagged(): void
    {
        $reader = User::factory()->create();
        $loan = Loan::factory()->active()->create(['user_id' => $reader->id, 'due_at' => now()->subDays(3)]);

        $this->as($reader)->postJson("/api/loans/{$loan->id}/renew")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Un emprunt en retard ne peut pas être prolongé : rendez le livre.');

        $this->getJson('/api/loans?status=active')->assertJsonPath('data.0.overdue', true)->assertJsonPath('data.0.days_left', -3);
    }

    public function test_only_owner_can_renew(): void
    {
        $loan = Loan::factory()->active()->create();

        $this->as(User::factory()->admin()->create())->postJson("/api/loans/{$loan->id}/renew")->assertForbidden();
    }

    public function test_loan_listing_scopes(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        Loan::factory()->active()->create(['user_id' => $alice->id]);
        Loan::factory()->returned()->create(['user_id' => $alice->id]);
        Loan::factory()->active()->create(['user_id' => $bob->id]);

        $this->as($alice);
        $this->assertCount(2, $this->getJson('/api/loans')->json('data'));
        $this->assertCount(1, $this->getJson('/api/loans?status=active')->json('data'));
        $this->assertCount(1, $this->getJson('/api/loans?status=returned')->json('data'));
        $this->getJson('/api/loans?scope=all')->assertForbidden();

        $this->as(User::factory()->admin()->create());
        $this->assertCount(3, $this->getJson('/api/loans?scope=all')->json('data'));
        $this->assertCount(2, $this->getJson('/api/loans?scope=all&status=active')->json('data'));
    }

    public function test_book_resource_exposes_status_and_hides_borrower_from_readers(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $loanBook = Book::factory()->create(['title' => 'Emprunté']);
        Book::factory()->create(['title' => 'Libre']);
        Book::factory()->create(['title' => 'Sur place', 'loanable' => false]);
        Loan::factory()->active()->create(['book_id' => $loanBook->id, 'user_id' => $alice->id]);

        $statuses = fn () => collect($this->getJson('/api/books?per_page=50')->json('data'))->pluck('loan_status', 'title')->all();

        // Un autre lecteur voit le statut mais pas le nom de l'emprunteur
        $this->as(User::factory()->create());
        $this->assertSame(['Sur place' => 'onsite', 'Libre' => 'available', 'Emprunté' => 'borrowed'], $statuses());
        $row = collect($this->getJson('/api/books?per_page=50')->json('data'))->firstWhere('title', 'Emprunté');
        $this->assertNull($row['current_loan']['borrower']);
        $this->assertFalse($row['current_loan']['mine']);

        // L'emprunteur se reconnaît ; l'admin voit le nom
        $this->as($alice);
        $this->assertTrue(collect($this->getJson('/api/books?per_page=50')->json('data'))->firstWhere('title', 'Emprunté')['current_loan']['mine']);
        $this->as(User::factory()->admin()->create());
        $this->assertSame('Alice', collect($this->getJson('/api/books?per_page=50')->json('data'))->firstWhere('title', 'Emprunté')['current_loan']['borrower']);
    }

    public function test_borrowed_book_cannot_be_deleted(): void
    {
        $book = Book::factory()->create();
        $loan = Loan::factory()->active()->create(['book_id' => $book->id]);
        $this->as(User::factory()->admin()->create());

        $this->deleteJson("/api/books/{$book->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ce livre est actuellement emprunté et ne peut pas être supprimé.');

        $this->postJson("/api/loans/{$loan->id}/return")->assertOk();
        $this->deleteJson("/api/books/{$book->id}")->assertNoContent();
    }

    public function test_database_prevents_two_active_loans_for_one_book(): void
    {
        $book = Book::factory()->create();
        Loan::factory()->active()->create(['book_id' => $book->id]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Loan::factory()->active()->create(['book_id' => $book->id]);
    }
}
