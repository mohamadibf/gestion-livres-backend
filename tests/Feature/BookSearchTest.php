<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->actingAs(User::factory()->create(), 'web');

        Book::factory()->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '111', 'genre' => 'Science-Fiction', 'published_year' => 1965]);
        Book::factory()->create(['title' => 'Les Misérables', 'author' => 'Victor Hugo', 'isbn' => '222', 'genre' => 'Roman', 'published_year' => 1862]);
        Book::factory()->create(['title' => 'Notre-Dame de Paris', 'author' => 'Victor Hugo', 'isbn' => '333', 'genre' => 'Roman', 'published_year' => 1831]);
    }

    private function titles(string $query = ''): array
    {
        return collect($this->getJson('/api/books'.$query)->assertOk()->json('data'))->pluck('title')->all();
    }

    public function test_search_matches_title_author_and_isbn(): void
    {
        $this->assertSame(['Dune'], $this->titles('?search=dun'));
        $this->assertEqualsCanonicalizing(['Les Misérables', 'Notre-Dame de Paris'], $this->titles('?search=hugo'));
        $this->assertSame(['Notre-Dame de Paris'], $this->titles('?search=333'));
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        $this->assertSame([], $this->titles('?search=%25'));
        $this->assertSame([], $this->titles('?search=_'));
    }

    public function test_filter_by_genre(): void
    {
        $this->assertSame(['Dune'], $this->titles('?genre=Science-Fiction'));
        $this->assertCount(2, $this->titles('?genre=Roman'));
        $this->assertSame([], $this->titles('?genre=Fantasy'));
    }

    public function test_sorting(): void
    {
        $this->assertSame(['Dune', 'Les Misérables', 'Notre-Dame de Paris'], $this->titles('?sort=title'));
        $this->assertSame(['Notre-Dame de Paris', 'Les Misérables', 'Dune'], $this->titles('?sort=year_asc'));
        $this->assertSame(['Dune', 'Les Misérables', 'Notre-Dame de Paris'], $this->titles('?sort=year_desc'));
        $this->assertSame('Frank Herbert', $this->getJson('/api/books?sort=author')->json('data.0.author'));
    }

    public function test_filters_combine_with_pagination_and_keep_query_string(): void
    {
        $response = $this->getJson('/api/books?genre=Roman&per_page=1&sort=title')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertStringContainsString('genre=Roman', $response->json('links.next'));
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->getJson('/api/books?sort=drop')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/books?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
}
