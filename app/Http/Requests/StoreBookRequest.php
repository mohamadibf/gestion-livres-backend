<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Book;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Book::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:20', Rule::unique('books', 'isbn')],
            'published_year' => ['required', 'integer', 'min:1000', 'max:'.date('Y')],
            'genre' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:50'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'shelf_mark' => ['nullable', 'string', 'max:50'],
            'physical_condition' => ['nullable', Rule::in(Book::CONDITIONS)],
            'keywords' => ['nullable', 'array', 'max:10'],
            'keywords.*' => ['string', 'max:30', 'distinct'],
            'loanable' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }
}
