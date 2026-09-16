<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Le compte de démonstration est réservé aux environnements local et de test.');
        }

        User::firstOrCreate(
            ['email' => 'admin@glv.test'],
            [
                'agence_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ],
        );
    }
}
