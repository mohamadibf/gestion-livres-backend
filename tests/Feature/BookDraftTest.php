<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    private function as(?User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $user ? $this->actingAs($user, 'web') : $this;
    }

    private function titles(?User $user, string $query = ''): array
    {
        return collect($this->as($user)->getJson('/api/books'.$query)->assertOk()->json('data'))->pluck('title')->sort()->values()->all();
    }

    public function test_draft_only_requires_title_and_author(): void
    {
        $this->as(User::factory()->admin()->create())
            ->postJson('/api/books', ['title' => 'Brouillon', 'author' => 'Anonyme', 'status' => 'draft'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.genre', null);

        // Plusieurs brouillons sans ISBN peuvent coexister
        $this->postJson('/api/books', ['title' => 'Autre', 'author' => 'Anonyme', 'status' => 'draft'])->assertCreated();

        $this->postJson('/api/books', ['author' => 'Sans titre', 'status' => 'draft'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_published_book_still_requires_full_record(): void
    {
        $this->as(User::factory()->admin()->create())
            ->postJson('/api/books', ['title' => 'Incomplet', 'author' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['isbn', 'published_year', 'genre']);
    }

    public function test_provided_draft_fields_are_still_validated(): void
    {
        $this->as(User::factory()->admin()->create())
            ->postJson('/api/books', ['title' => 'T', 'author' => 'A', 'status' => 'draft', 'published_year' => 3000])
            ->assertUnprocessable()->assertJsonValidationErrors('published_year');
    }

    public function test_only_admins_see_drafts_in_listing(): void
    {
        Book::factory()->create(['title' => 'Publié']);
        Book::factory()->create(['title' => 'Brouillon', 'status' => 'draft', 'isbn' => null]);

        $this->assertSame(['Publié'], $this->titles(null));
        $this->assertSame(['Publié'], $this->titles(User::factory()->create()));
        // Un lecteur ne peut pas forcer l'affichage des brouillons
        $this->assertSame(['Publié'], $this->titles(User::factory()->create(), '?status=draft'));

        $admin = User::factory()->admin()->create();
        $this->assertSame(['Brouillon', 'Publié'], $this->titles($admin));
        $this->assertSame(['Brouillon'], $this->titles($admin, '?status=draft'));
        $this->assertSame(['Publié'], $this->titles($admin, '?status=published'));
    }

    public function test_draft_detail_is_hidden_with_404(): void
    {
        $draft = Book::factory()->create(['status' => 'draft']);
        $published = Book::factory()->create();

        $this->as(null)->getJson("/api/books/{$draft->id}")->assertNotFound();
        $this->as(User::factory()->create())->getJson("/api/books/{$draft->id}")->assertNotFound();
        $this->as(User::factory()->admin()->create())->getJson("/api/books/{$draft->id}")->assertOk();
        $this->as(null)->getJson("/api/books/{$published->id}")->assertOk();
    }

    public function test_publishing_requires_a_complete_record(): void
    {
        $draft = Book::factory()->create(['status' => 'draft', 'isbn' => null, 'genre' => null, 'published_year' => null]);
        $this->as(User::factory()->admin()->create());

        // Modifier un brouillon sans changer son statut n'exige rien de plus
        $this->putJson("/api/books/{$draft->id}", ['title' => 'Nouveau titre'])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->putJson("/api/books/{$draft->id}", ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors(['isbn', 'genre', 'published_year']);

        $this->putJson("/api/books/{$draft->id}", [
            'status' => 'published', 'isbn' => '978-1', 'genre' => 'Roman', 'published_year' => 2001,
        ])->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_published_book_can_go_back_to_draft_and_is_then_hidden(): void
    {
        $book = Book::factory()->create(['title' => 'Retiré']);
        $this->as(User::factory()->admin()->create())->putJson("/api/books/{$book->id}", ['status' => 'draft'])->assertOk();

        $this->assertSame([], $this->titles(null));
    }

    public function test_cannot_empty_required_fields_of_a_published_book(): void
    {
        $book = Book::factory()->create();

        $this->as(User::factory()->admin()->create())
            ->putJson("/api/books/{$book->id}", ['isbn' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('isbn');
    }

    public function test_draft_cannot_be_borrowed(): void
    {
        $draft = Book::factory()->create(['status' => 'draft']);

        $this->as(User::factory()->create())->postJson("/api/books/{$draft->id}/borrow")
            ->assertUnprocessable()->assertJsonPath('message', "Ce livre n'est pas encore publié.");
    }

    public function test_guest_sees_availability_without_borrower_names(): void
    {
        $book = Book::factory()->create(['title' => 'Emprunté']);
        Loan::factory()->active()->create(['book_id' => $book->id, 'user_id' => User::factory()->create(['name' => 'Alice'])->id]);

        $row = $this->as(null)->getJson('/api/books')->assertOk()->json('data.0');

        $this->assertSame('borrowed', $row['loan_status']);
        $this->assertNull($row['current_loan']['borrower']);
        $this->assertFalse($row['current_loan']['mine']);
        $this->assertStringNotContainsString('Alice', json_encode($row));
    }

    public function test_guest_cannot_borrow(): void
    {
        $this->as(null)->postJson('/api/books/'.Book::factory()->create()->id.'/borrow')->assertUnauthorized();
    }
}
