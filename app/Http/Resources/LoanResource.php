<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Loan */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $this->user_id;
        $settings = app(\App\Services\Settings::class);
        $max = (int) $settings->get('max_renewals');
        $dueSoon = $this->isActive() && ! $this->isOverdue() && $this->daysLeft() <= (int) $settings->get('due_soon_days');

        return [
            'id' => $this->id,
            'book' => $this->whenLoaded('book', fn () => [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'author' => $this->book->author,
                'shelf_mark' => $this->book->shelf_mark,
                'image_path' => $this->book->image_path,
            ]),
            // L'identité de l'emprunteur n'est visible que de l'emprunteur lui-même et des admins
            'user' => $this->when($viewer && ($viewer->isAdmin() || $isOwner), fn () => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ])),
            'borrowed_at' => $this->borrowed_at,
            'due_at' => $this->due_at,
            'returned_at' => $this->returned_at,
            'renewals_count' => $this->renewals_count,
            'active' => $this->isActive(),
            'overdue' => $this->isOverdue(),
            'due_soon' => $dueSoon,
            'days_left' => $this->isActive() ? $this->daysLeft() : null,
            'mine' => $isOwner,
            'can_return' => $this->isActive() && ($isOwner || ($viewer && $viewer->isAdmin())),
            'can_renew' => $this->isActive() && $isOwner && ! $this->isOverdue() && $this->renewals_count < $max,
        ];
    }
}
