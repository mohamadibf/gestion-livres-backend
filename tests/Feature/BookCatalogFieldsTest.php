<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCatalogFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs(User::factory()->admin()->create(), 'web');
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Les Chants de Maldoror',
            'author' => 'Lautréamont',
            'isbn' => '978-2-07-036822-8',
            'published_year' => 1869,
            'genre' => 'Roman',
        ], $extra);
    }

    public function test_defaults_when_fields_are_omitted(): void
    {
        $this->postJson('/api/books', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.physical_condition', 'excellent')
            ->assertJsonPath('data.loanable', true)
            ->assertJsonPath('data.keywords', [])
            ->assertJsonPath('data.shelf_mark', null);
    }

    public function test_create_with_all_fields(): void
    {
        $this->postJson('/api/books', $this->payload([
            'shelf_mark' => '841.8 LAUT-mald / R-04',
            'physical_condition' => 'restore',
            'keywords' => ['Surréalisme', 'Plein maroquin'],
            'loanable' => false,
        ]))->assertCreated()
            ->assertJsonPath('data.shelf_mark', '841.8 LAUT-mald / R-04')
            ->assertJsonPath('data.physical_condition', 'restore')
            ->assertJsonPath('data.keywords', ['Surréalisme', 'Plein maroquin'])
            ->assertJsonPath('data.loanable', false);
    }

    public function test_validation_rules(): void
    {
        $this->postJson('/api/books', $this->payload(['physical_condition' => 'neuf']))
            ->assertUnprocessable()->assertJsonValidationErrors('physical_condition');

        $this->postJson('/api/books', $this->payload(['keywords' => array_map(fn ($i) => "mot$i", range(1, 11))]))
            ->assertUnprocessable()->assertJsonValidationErrors('keywords');

        $this->postJson('/api/books', $this->payload(['keywords' => ['a', 'a']]))
            ->assertUnprocessable()->assertJsonValidationErrors('keywords.0');

        $this->postJson('/api/books', $this->payload(['keywords' => [str_repeat('x', 31)]]))
            ->assertUnprocessable()->assertJsonValidationErrors('keywords.0');

        $this->postJson('/api/books', $this->payload(['shelf_mark' => str_repeat('x', 51)]))
            ->assertUnprocessable()->assertJsonValidationErrors('shelf_mark');
    }

    public function test_update_can_clear_keywords_and_shelf_mark(): void
    {
        $book = Book::factory()->create(['shelf_mark' => 'A-1', 'keywords' => ['x', 'y']]);

        $this->putJson("/api/books/{$book->id}", ['keywords' => null, 'shelf_mark' => null])
            ->assertOk()
            ->assertJsonPath('data.keywords', [])
            ->assertJsonPath('data.shelf_mark', null);
    }

    public function test_update_toggles_loanable(): void
    {
        $book = Book::factory()->create();

        $this->putJson("/api/books/{$book->id}", ['loanable' => false])->assertOk()->assertJsonPath('data.loanable', false);
        $this->putJson("/api/books/{$book->id}", ['title' => 'Autre titre'])->assertOk()->assertJsonPath('data.loanable', false);
    }

    public function test_search_matches_shelf_mark(): void
    {
        Book::factory()->create(['title' => 'A', 'shelf_mark' => 'LIT-PRO-01']);
        Book::factory()->create(['title' => 'B', 'shelf_mark' => 'SF-HER-04']);

        $this->assertSame(['B'], collect($this->getJson('/api/books?search=SF-HER')->json('data'))->pluck('title')->all());
    }

    public function test_reader_cannot_set_catalog_fields(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/books', $this->payload(['shelf_mark' => 'X']))->assertForbidden();
    }

    public function test_accepts_the_form_encoding_sent_by_the_frontend(): void
    {
        // FormData : keywords[]=..., booléens '1'/'0', tableau vide => champ vide
        $created = $this->post('/api/books', $this->payload([
            'keywords' => ['Surréalisme', 'Papier vélin'],
            'loanable' => '0',
            'physical_condition' => 'good',
            'shelf_mark' => '',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(['Surréalisme', 'Papier vélin'], $created->json('data.keywords'));
        $this->assertFalse($created->json('data.loanable'));
        $this->assertNull($created->json('data.shelf_mark'));

        $id = $created->json('data.id');
        $this->post("/api/books/{$id}", ['_method' => 'PUT', 'keywords' => '', 'loanable' => '1'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.keywords', [])
            ->assertJsonPath('data.loanable', true);
    }
}
