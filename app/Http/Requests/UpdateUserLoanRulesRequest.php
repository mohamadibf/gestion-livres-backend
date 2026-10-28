<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserLoanRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /** null (ou champ vide) = retour au réglage global. */
    public function rules(): array
    {
        return [
            'loan_limit' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'loan_duration_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function attributes(): array
    {
        return [
            'loan_limit' => "limite d'emprunts",
            'loan_duration_days' => 'durée du prêt',
        ];
    }
}
