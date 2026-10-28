<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Contracts\Validation\Validator;

class UpdateBookRequest extends StoreBookRequest
{
    /** Champs exigés pour qu'un livre soit publié */
    private const REQUIRED_TO_PUBLISH = ['title', 'author', 'isbn', 'published_year', 'genre'];

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('book')) ?? false;
    }

    protected function targetStatus(): string
    {
        return $this->input('status', $this->route('book')->status);
    }

    protected function bookToIgnore(): ?Book
    {
        return $this->route('book');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // En modification, un champ absent de la requête est conservé tel quel
        foreach (['title', 'author', 'isbn', 'published_year', 'genre'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }

    /**
     * Publier (ou rester publié) exige une fiche complète : on contrôle les valeurs finales,
     * c'est-à-dire celles de la requête complétées par celles déjà enregistrées.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->targetStatus() === Book::STATUS_DRAFT) {
                return;
            }

            $book = $this->route('book');

            foreach (self::REQUIRED_TO_PUBLISH as $field) {
                $value = $this->has($field) ? $this->input($field) : $book->{$field};

                if ($value === null || $value === '') {
                    $validator->errors()->add($field, 'Ce champ est obligatoire pour publier le livre.');
                }
            }
        }];
    }
}
