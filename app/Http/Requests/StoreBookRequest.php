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

    /** Statut visé par la requête : un brouillon n'exige que le titre et l'auteur. */
    protected function targetStatus(): string
    {
        return $this->input('status', Book::STATUS_PUBLISHED);
    }

    /** Livre exclu du contrôle d'unicité de l'ISBN (celui qu'on modifie). */
    protected function bookToIgnore(): ?Book
    {
        return null;
    }

    public function rules(): array
    {
        $publishing = $this->targetStatus() !== Book::STATUS_DRAFT;
        $needed = $publishing ? 'required' : 'nullable';

        return [
            'status' => ['sometimes', Rule::in([Book::STATUS_PUBLISHED, Book::STATUS_DRAFT])],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [$needed, 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($this->bookToIgnore())],
            'published_year' => [$needed, 'integer', 'min:1000', 'max:'.date('Y')],
            'genre' => [$needed, 'string', 'max:100'],
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
