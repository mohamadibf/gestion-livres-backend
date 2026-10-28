<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simule une requête issue du SPA (domaine stateful Sanctum)
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'isbn' => '9780441172719',
            'published_year' => 1965,
            'genre' => 'Science-fiction',
        ], $overrides);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/books')->assertUnauthorized();
        $this->postJson('/api/books', $this->payload())->assertUnauthorized();
    }

    public function test_unverified_user_gets_403(): void
    {
        $this->actingAs(User::factory()->unverified()->create(), 'web')
            ->getJson('/api/books')->assertForbidden();
    }

    public function test_reader_can_read_but_not_write(): void
    {
        $book = Book::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader, 'web');
        $this->getJson('/api/books')->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
        $this->getJson("/api/books/{$book->id}")->assertOk();
        $this->postJson('/api/books', $this->payload())->assertForbidden();
        $this->putJson("/api/books/{$book->id}", ['title' => 'X'])->assertForbidden();
        $this->deleteJson("/api/books/{$book->id}")->assertForbidden();
    }

    public function test_admin_crud_with_image(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create(), 'web');

        $response = $this->post('/api/books', $this->payload() + [
            'image' => UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
        ], ['Accept' => 'application/json'])->assertCreated();

        $book = Book::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists($book->image_path);

        $this->putJson("/api/books/{$book->id}", ['title' => 'Dune Messiah', 'isbn' => $book->isbn])
            ->assertOk()->assertJsonPath('data.title', 'Dune Messiah');

        $this->postJson('/api/books', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('isbn');

        $this->deleteJson("/api/books/{$book->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($book->image_path);
    }
}
