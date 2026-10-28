<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Book */
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loan = $this->relationLoaded('activeLoan') ? $this->activeLoan : null;
        $viewer = $request->user();

        $status = match (true) {
            $loan !== null && $loan->isOverdue() => 'overdue',
            $loan !== null => 'borrowed',
            ! $this->loanable => 'onsite',
            default => 'available',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_year' => $this->published_year,
            'genre' => $this->genre,
            'description' => $this->description,
            'page_count' => $this->page_count,
            'language' => $this->language,
            'publisher' => $this->publisher,
            'shelf_mark' => $this->shelf_mark,
            'physical_condition' => $this->physical_condition,
            'keywords' => $this->keywords ?? [],
            'loanable' => $this->loanable,
            'status' => $this->status,
            'loan_status' => $status,
            'current_loan' => $loan ? [
                'id' => $loan->id,
                'due_at' => $loan->due_at,
                'days_left' => $loan->daysLeft(),
                'overdue' => $loan->isOverdue(),
                'due_soon' => ! $loan->isOverdue() && $loan->daysLeft() <= (int) app(\App\Services\Settings::class)->get('due_soon_days'),
                'mine' => $viewer?->id === $loan->user_id,
                // Le nom de l'emprunteur n'est donné qu'aux admins
                'borrower' => $viewer?->isAdmin() ? $loan->user?->name : null,
            ] : null,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
