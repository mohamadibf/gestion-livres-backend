<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    // Le catalogue est public : l'utilisateur peut être absent (visiteur)
    public function viewAny(?User $user): bool
    {
        return true;
    }

    // Un brouillon n'est visible que des administrateurs
    public function view(?User $user, Book $book): bool
    {
        return ! $book->isDraft() || (bool) $user?->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }
}
