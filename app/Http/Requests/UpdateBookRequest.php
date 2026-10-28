<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBookRequest extends StoreBookRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('book')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['isbn'] = ['sometimes', 'required', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($this->route('book'))];

        foreach (['title', 'author', 'published_year', 'genre'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
