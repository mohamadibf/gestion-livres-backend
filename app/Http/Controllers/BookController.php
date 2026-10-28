<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Book::class);

        $perPage = min(max((int) $request->input('per_page', 6), 1), 50);

        return BookResource::collection(Book::latest()->paginate($perPage));
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
