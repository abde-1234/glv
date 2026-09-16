<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $agences = [
            ['nom' => 'Atlas Rent', 'ville' => 'Casablanca', 'statut' => 'actif', 'plan' => 'Pro', 'expiration' => 300],
            ['nom' => 'Marrakech Cars', 'ville' => 'Marrakech', 'statut' => 'essai', 'plan' => 'Essai', 'expiration' => 20],
            ['nom' => 'Agadir Drive', 'ville' => 'Agadir', 'statut' => 'actif', 'plan' => 'Premium', 'expiration' => 180],
            ['nom' => 'Rabat Auto', 'ville' => 'Rabat', 'statut' => 'suspendu', 'plan' => 'Basic', 'expiration' => 90],
            ['nom' => 'Fès Location', 'ville' => 'Fès', 'statut' => 'actif', 'plan' => 'Pro', 'expiration' => 250],
            ['nom' => 'Casa Prestige', 'ville' => 'Casablanca', 'statut' => 'expire', 'plan' => 'Basic', 'expiration' => -30],
        ];

        foreach ($agences as $index => $data) {
            $slug = str($data['nom'])->ascii()->slug();
            $agence = Agence::updateOrCreate(
                ['email' => "contact@{$slug}.test"],
                [
                    'nom' => $data['nom'],
                    'telephone' => sprintf('06 10 20 30 %02d', $index + 1),
                    'ville' => $data['ville'],
                    'adresse' => "Centre-ville, {$data['ville']}",
                    'statut' => $data['statut'],
                    'type_abonnement' => $data['plan'],
                    'date_expiration' => today()->addDays($data['expiration']),
                ],
            );

            User::updateOrCreate(
                ['email' => "gerant@{$slug}.test"],
                [
                    'agence_id' => $agence->id,
                    'name' => ['Ahmed Benali', 'Yassine Amrani', 'Salma Ouahbi', 'Karim Alaoui', 'Imane Idrissi', 'Nadia Mansouri'][$index],
                    'telephone' => sprintf('06 22 33 44 %02d', $index + 1),
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_ADMIN_AGENCE,
                ],
            );

            $voiture = Voiture::updateOrCreate(
                [
                    'agence_id' => $agence->id,
                    'immatriculation' => sprintf('%d-A-%d', 12000 + $index, 10 + $index),
                ],
                [
                    'marque' => ['Renault', 'Peugeot', 'Dacia', 'Hyundai', 'Toyota', 'Volkswagen'][$index],
                    'modele' => ['Clio', '208', 'Duster', 'i20', 'Corolla', 'Golf'][$index],
                    'categorie' => 'Berline',
                    'annee' => 2024,
                    'prix_jour' => 420 + ($index * 35),
                    'kilometrage' => 12000 + ($index * 2500),
                    'statut' => 'disponible',
                ],
            );

            $client = Client::updateOrCreate(
                [
                    'agence_id' => $agence->id,
                    'email' => "client{$index}@glv.test",
                ],
                [
                    'nom' => "Client Démo {$index}",
                    'telephone' => sprintf('06 55 66 77 %02d', $index + 1),
                    'ville' => $data['ville'],
                ],
            );

            foreach (range(0, 5) as $monthOffset) {
                $start = today()->subMonths($monthOffset)->startOfMonth()->addDays($index + 2);

                Reservation::updateOrCreate(
                    [
                        'agence_id' => $agence->id,
                        'client_id' => $client->id,
                        'voiture_id' => $voiture->id,
                        'date_debut' => $start,
                    ],
                    [
                        'date_fin' => $start->copy()->addDays(3),
                        'montant' => 1600 + ($index * 120),
                        'statut' => 'confirmee',
                    ],
                );
            }
        }
    }
}
