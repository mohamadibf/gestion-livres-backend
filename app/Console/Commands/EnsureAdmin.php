<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnsureAdmin extends Command
{
    protected $signature = 'users:ensure-admin';

    protected $description = "Crée le premier administrateur à partir de ADMIN_EMAIL, ADMIN_NAME et ADMIN_PASSWORD (sans effet s'il en existe déjà un)";

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->info('Un administrateur existe déjà : rien à faire.');

            return self::SUCCESS;
        }

        $email = config('app.initial_admin.email');
        $password = config('app.initial_admin.password');

        if (! $email || ! $password) {
            $this->warn('Aucun administrateur et ADMIN_EMAIL / ADMIN_PASSWORD non définis : aucun compte créé.');

            return self::SUCCESS;
        }

        if (strlen($password) < 8) {
            $this->error('ADMIN_PASSWORD doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        $user = new User(['name' => config('app.initial_admin.name'), 'email' => $email, 'password' => $password]);
        // Rôle et vérification d'email ne sont volontairement pas assignables en masse
        $user->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

        $this->info("Administrateur créé : {$email}");

        return self::SUCCESS;
    }
}
