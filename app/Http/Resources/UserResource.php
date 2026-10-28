<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Vue d'un compte pour l'administration (jamais exposée aux lecteurs). */
/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'verified' => $this->hasVerifiedEmail(),
            'created_at' => $this->created_at,
            // Exceptions (null = réglage global)
            'loan_limit' => $this->loan_limit,
            'loan_duration_days' => $this->loan_duration_days,
            'effective_loan_limit' => $this->effectiveLoanLimit(),
            'effective_loan_duration_days' => $this->effectiveLoanDuration(),
            'active_loans_count' => $this->whenCounted('activeLoans'),
        ];
    }
}
