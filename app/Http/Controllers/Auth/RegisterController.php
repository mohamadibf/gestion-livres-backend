<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        // Le rôle n'est jamais fourni par le client : la colonne vaut "reader" par défaut.
        $user = User::create($request->validated());

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json($user, 201);
    }
}
