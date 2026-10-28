<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (comptes de développement, mot de passe : "password").
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrateur',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Lecteur',
            'email' => 'reader@example.com',
        ]);
    }
}
