<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 6);
        $books = Book::latest()->paginate($perPage);

        return response()->json([
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
            'links' => [
                'first' => $books->url(1),
                'last' => $books->url($books->lastPage()),
                'prev' => $books->previousPageUrl(),
                'next' => $books->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books|max:20',
            'published_year' => 'required|integer',
            'genre' => 'required|string',
            'description' => 'nullable|string',
            'page_count' => 'nullable|integer',
            'language' => 'nullable|string',
            'publisher' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('books', 'public');
            $validated['image_path'] = $imagePath;
        }

        $book = Book::create($validated);
        return response()->json($book, 201);
    }

    public function show(Book $book)
    {
        return response()->json($book);
    }

    public function update(Request $request, Book $book)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'author' => 'sometimes|string|max:255',
            'isbn' => 'sometimes|string|unique:books,isbn,' . $book->id . '|max:20',
            'published_year' => 'sometimes|integer',
            'genre' => 'sometimes|string',
            'description' => 'nullable|string',
            'page_count' => 'nullable|integer',
            'language' => 'nullable|string',
            'publisher' => 'nullable|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);




        if ($request->hasFile('image')) {
            if ($book->image_path) {
                Storage::delete('public/' . $book->image_path);
            }
            $path = $request->file('image')->store('books', 'public');;
            $validated['image_path'] = $path;
        }

        $updated = $book->update($validated);


        return response()->json([
            'success' => $updated,
            'changes' => $book->getChanges(),
            'book' => $book->fresh()
        ]);
    }

    public function destroy(Book $book)
    {
        if ($book->image_path) {
            Storage::delete(str_replace('storage/', 'public/', $book->image_path));
        }

        $book->delete();
        return response()->noContent();
    }
}
