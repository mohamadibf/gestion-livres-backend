<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'library_name' => ['sometimes', 'required', 'string', 'max:60'],
            'loan_duration_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            // 0 = illimité
            'max_active_loans' => ['sometimes', 'required', 'integer', 'min:0', 'max:50'],
            'max_renewals' => ['sometimes', 'required', 'integer', 'min:0', 'max:10'],
            'due_soon_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'library_name' => 'nom de la bibliothèque',
            'loan_duration_days' => 'durée du prêt',
            'max_active_loans' => "nombre d'emprunts simultanés",
            'max_renewals' => 'nombre de prolongations',
            'due_soon_days' => 'seuil « bientôt à rendre »',
        ];
    }
}
