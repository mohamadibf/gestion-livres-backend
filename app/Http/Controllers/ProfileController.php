<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** Modifie nom et email. Changer d'email exige le mot de passe et redemande la vérification. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ]);

        $emailChanged = strtolower($data['email']) !== strtolower($user->email);

        if ($emailChanged) {
            $this->assertPassword($user, $data['current_password'] ?? null);
        }

        $user->name = $data['name'];

        if ($emailChanged) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json($user->fresh());
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.different' => "Le nouveau mot de passe doit être différent de l'actuel.",
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        // Nouvel identifiant de session après un changement de secret
        $request->session()->regenerate();

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $this->assertPassword($user, $request->input('current_password'));

        if ($user->activeLoans()->exists()) {
            throw ValidationException::withMessages(['account' => 'Rendez votre livre emprunté avant de supprimer votre compte.']);
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['account' => "Vous êtes le dernier administrateur : nommez-en un autre avant de supprimer ce compte."]);
        }

        DB::transaction(function () use ($user, $request) {
            Auth::guard('web')->logout();
            $user->delete(); // l'historique de prêts est supprimé avec le compte (clé étrangère en cascade)

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        });

        return response()->noContent();
    }

    /** Emprunt en cours et statistiques du lecteur connecté. */
    public function stats(Request $request)
    {
        $user = $request->user();
        $loans = Loan::where('user_id', $user->id);

        $active = (clone $loans)->active()->with('book')->orderBy('due_at')->first();

        $lateReturns = (clone $loans)->whereNotNull('returned_at')->whereColumn('returned_at', '>', 'due_at')->count();
        $overdueNow = (clone $loans)->active()->where('due_at', '<', now()->startOfDay())->count();

        return response()->json(['data' => [
            'active_loan' => $active ? (new LoanResource($active->setRelation('user', $user)))->resolve($request) : null,
            'active_count' => (clone $loans)->active()->count(),
            'total_loans' => (clone $loans)->count(),
            'returned_loans' => (clone $loans)->whereNotNull('returned_at')->count(),
            // Retards = rendus après la date prévue + emprunts actuellement en retard
            'late_loans' => $lateReturns + $overdueNow,
            'loan_limit' => $user->effectiveLoanLimit(),
            'loan_duration_days' => $user->effectiveLoanDuration(),
        ]]);
    }

    private function assertPassword(User $user, ?string $password): void
    {
        if (! $password || ! \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
    }
}
