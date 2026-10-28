<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'borrowed_at' => now(),
            'due_at' => now()->addDays((int) config('loans.duration_days')),
        ];
    }

    /** Prêt en cours : renseigne la clé d'unicité par livre. */
    public function active(): static
    {
        return $this->afterCreating(fn ($loan) => $loan->forceFill(['active_book_id' => $loan->book_id])->save());
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'borrowed_at' => now()->subDays(10),
            'due_at' => now()->addDays(11),
            'returned_at' => now()->subDay(),
        ]);
    }
}
