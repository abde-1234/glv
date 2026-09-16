<?php

namespace Tests\Concerns;

use App\Models\Agence;
use App\Models\AgenceDocument;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Database\Eloquent\Model;

trait CreatesAgencyFixtures
{
    private function tenant(string $suffix, array $agencyAttributes = []): array
    {
        return Model::withoutEvents(function () use ($suffix, $agencyAttributes): array {
            $agency = Agence::create(array_merge([
                'nom' => 'Agence '.$suffix, 'email' => $suffix.'@agence.test', 'ville' => 'Fès',
                'adresse' => '12 avenue Atlas', 'telephone' => '0612345678', 'statut' => 'actif',
                'date_expiration' => today()->addYear(), 'type_abonnement' => 'Pro',
            ], $agencyAttributes));
            $admin = User::factory()->create(['agence_id' => $agency->id, 'role' => User::ROLE_ADMIN_AGENCE]);
            $employee = User::factory()->create(['agence_id' => $agency->id, 'role' => User::ROLE_EMPLOYE]);
            $client = Client::create(['agence_id' => $agency->id, 'nom' => 'Client '.$suffix,
                'telephone' => '0612345678', 'cin' => 'CIN-'.$suffix, 'email' => $suffix.'@client.test', 'ville' => 'Fès', 'statut' => 'actif']);
            $car = Voiture::create(['agence_id' => $agency->id, 'marque' => 'Peugeot', 'modele' => '208 '.$suffix,
                'immatriculation' => 'IMM-'.$suffix, 'prix_jour' => 300, 'statut' => 'disponible']);
            $reservation = Reservation::create(['agence_id' => $agency->id, 'client_id' => $client->id,
                'voiture_id' => $car->id, 'reference' => 'RES-2026-'.$suffix, 'date_debut' => '2026-12-10',
                'date_fin' => '2026-12-12', 'prix_jour' => 300, 'montant' => 600, 'statut' => 'confirmee']);
            $contract = Contrat::create(['agence_id' => $agency->id, 'client_id' => $client->id,
                'voiture_id' => $car->id, 'reservation_id' => $reservation->id, 'reference' => 'CTR-2026-'.$suffix,
                'date_debut' => '2026-12-10', 'date_fin' => '2026-12-12', 'montant' => 600, 'statut' => 'actif']);
            $document = AgenceDocument::create(['agence_id' => $agency->id, 'nom' => 'Document '.$suffix,
                'type' => 'autre', 'fichier' => 'agences/'.$agency->id.'/documents/'.$suffix.'.pdf', 'taille' => 15]);

            return compact('agency', 'admin', 'employee', 'client', 'car', 'reservation', 'contract', 'document');
        });
    }
}
