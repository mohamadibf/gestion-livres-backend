<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserLoanRulesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Réservé aux administrateurs.');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = User::query()->withCount(['loans as active_loans_count' => fn ($q) => $q->whereNull('returned_at')]);

        if ($search = trim($filters['search'] ?? '')) {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->whereLike('name', $term)->orWhereLike('email', $term));
        }

        return UserResource::collection(
            $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 10)->withQueryString()
        );
    }

    /** Définit (ou retire) les exceptions de prêt d'un lecteur. */
    public function update(UpdateUserLoanRulesRequest $request, User $user)
    {
        // forceFill : ces colonnes ne sont volontairement pas assignables en masse
        $user->forceFill($request->validated())->save();

        return new UserResource($user->loadCount(['loans as active_loans_count' => fn ($q) => $q->whereNull('returned_at')]));
    }
}
