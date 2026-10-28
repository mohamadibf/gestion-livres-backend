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
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Book::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'title', 'author', 'year_asc', 'year_desc'])],
            'status' => ['nullable', Rule::in([Book::STATUS_PUBLISHED, Book::STATUS_DRAFT])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Book::query()->with('activeLoan.user');

        // Visiteurs et lecteurs ne voient jamais les brouillons ; les admins peuvent filtrer par statut
        if (! $request->user()?->isAdmin()) {
            $query->where('status', Book::STATUS_PUBLISHED);
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search = trim($filters['search'] ?? '')) {
            // Les jokers LIKE saisis par l'utilisateur sont échappés pour être recherchés littéralement
            $term = '%'.addcslashes($search, '%_\\').'%';
            // whereLike ignore la casse sur tous les SGBD (ILIKE sur Postgres, où LIKE est sensible à la casse)
            $query->where(fn ($q) => $q
                ->whereLike('title', $term)
                ->orWhereLike('author', $term)
                ->orWhereLike('isbn', $term)
                ->orWhereLike('shelf_mark', $term));
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

        // refresh() : renvoie aussi les valeurs par défaut posées par la base
        return (new BookResource(Book::create($data)->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, Book $book)
    {
        // 404 plutôt que 403 : on ne révèle pas l'existence d'un brouillon
        abort_if($book->isDraft() && ! $request->user()?->isAdmin(), 404);

        return new BookResource($book->load('activeLoan.user'));
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $this->deleteImage($book);
            $data['image_path'] = $request->file('image')->store('books', 'public');
        }

        $book->update($data);

        return new BookResource($book->fresh('activeLoan.user'));
    }

    public function destroy(Book $book)
    {
        Gate::authorize('delete', $book);

        if ($book->activeLoan()->exists()) {
            throw ValidationException::withMessages(['book' => 'Ce livre est actuellement emprunté et ne peut pas être supprimé.']);
        }

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
