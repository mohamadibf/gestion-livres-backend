<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Book::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'title', 'author', 'year_asc', 'year_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Book::query();

        if ($search = trim($filters['search'] ?? '')) {
            // Les jokers LIKE saisis par l'utilisateur sont échappés pour être recherchés littéralement
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q
                ->where('title', 'like', $term)
                ->orWhere('author', 'like', $term)
                ->orWhere('isbn', 'like', $term));
        }

        if (! empty($filters['genre'])) {
            $query->where('genre', $filters['genre']);
        }

        match ($filters['sort'] ?? 'recent') {
            'title' => $query->orderBy('title'),
            'author' => $query->orderBy('author'),
            'year_asc' => $query->orderBy('published_year'),
            'year_desc' => $query->orderByDesc('published_year'),
            default => $query->latest(),
        };

        // Second critère pour garder une pagination stable en cas d'égalité
        $query->orderByDesc('id');

        return BookResource::collection($query->paginate($filters['per_page'] ?? 6)->withQueryString());
    }

    public function store(StoreBookRequest $request)
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('books', 'public');
        }

        return (new BookResource(Book::create($data)))->response()->setStatusCode(201);
    }

    public function show(Book $book)
    {
        Gate::authorize('view', $book);

        return new BookResource($book);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $this->deleteImage($book);
            $data['image_path'] = $request->file('image')->store('books', 'public');
        }

        $book->update($data);

        return new BookResource($book->fresh());
    }

    public function destroy(Book $book)
    {
        Gate::authorize('delete', $book);

        $this->deleteImage($book);
        $book->delete();

        return response()->noContent();
    }

    private function deleteImage(Book $book): void
    {
        if ($book->image_path) {
            Storage::disk('public')->delete($book->image_path);
        }
    }
}
