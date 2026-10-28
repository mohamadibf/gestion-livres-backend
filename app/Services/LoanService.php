<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function borrow(User $user, Book $book): Loan
    {
        try {
            return DB::transaction(function () use ($user, $book) {
                // Verrouille les lignes pour éviter deux prêts simultanés du même livre ou du même lecteur
                $book = Book::whereKey($book->id)->lockForUpdate()->firstOrFail();
                User::whereKey($user->id)->lockForUpdate()->first();

                if ($book->isDraft()) {
                    $this->fail("Ce livre n'est pas encore publié.");
                }

                if (! $book->loanable) {
                    $this->fail('Ce livre est réservé à la consultation sur place.');
                }

                if ($book->activeLoan()->exists()) {
                    $this->fail('Ce livre est déjà emprunté.');
                }

                $max = $user->effectiveLoanLimit();
                if ($max > 0 && Loan::active()->where('user_id', $user->id)->count() >= $max) {
                    $this->fail($max === 1
                        ? 'Vous avez déjà un livre emprunté. Rendez-le avant d\'en emprunter un autre.'
                        : "Vous avez atteint la limite de {$max} livres empruntés en même temps.");
                }

                return Loan::create([
                    'book_id' => $book->id,
                    'user_id' => $user->id,
                    'borrowed_at' => now(),
                    'due_at' => now()->addDays($user->effectiveLoanDuration()),
                    'active_book_id' => $book->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('Ce livre vient d\'être emprunté par quelqu\'un d\'autre.');
        }
    }

    public function return(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                $this->fail('Ce livre a déjà été rendu.');
            }

            $loan->forceFill(['returned_at' => now(), 'active_book_id' => null])->save();

            return $loan;
        });
    }

    public function renew(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                $this->fail('Ce livre a déjà été rendu.');
            }
            if ($loan->isOverdue()) {
                $this->fail('Un emprunt en retard ne peut pas être prolongé : rendez le livre.');
            }
            if ($loan->renewals_count >= (int) app(Settings::class)->get('max_renewals')) {
                $this->fail('Cet emprunt a déjà été prolongé.');
            }

            $loan->forceFill([
                // Une prolongation ajoute la durée de prêt applicable à l'emprunteur
                'due_at' => $loan->due_at->copy()->addDays($loan->user->effectiveLoanDuration()),
                'renewals_count' => $loan->renewals_count + 1,
            ])->save();

            return $loan;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['book' => $message]);
    }
}
