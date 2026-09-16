<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_are_searchable_filterable_paginated_and_scoped(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');

        foreach (range(1, 9) as $index) {
            $this->createClient($agencyA, ['nom' => "Client A {$index}", 'ville' => $index === 1 ? 'Fès' : 'Rabat']);
        }
        $this->createClient($agencyB, ['nom' => 'Client B Secret', 'ville' => 'Fès']);

        $this->actingAs($adminA)
            ->get(route('agence.clients.index'))
            ->assertOk()
            ->assertViewHas('clients', fn ($clients) => $clients->total() === 9 && $clients->count() === 8)
            ->assertDontSee('Client B Secret');

        $this->actingAs($adminA)
            ->get(route('agence.clients.index', ['q' => 'Client A 1', 'ville' => 'Fès', 'statut' => 'actif']))
            ->assertOk()
            ->assertSee('Client A 1')
            ->assertDontSee('Client A 2');
    }

    public function test_client_crud_forces_current_agency_and_foreign_client_is_forbidden(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');
        $foreignClient = $this->createClient($agencyB, ['nom' => 'Client B']);

        $response = $this->actingAs($adminA)->post(route('agence.clients.store'), [
            'agence_id' => $agencyB->id,
            'nom' => 'Client Nouveau',
            'telephone' => '06 10 20 30 40',
            'cin' => 'AE123456',
            'email' => 'client@example.test',
            'ville' => 'Fès',
            'adresse' => '12 rue Atlas',
            'notes' => 'Client prioritaire',
            'statut' => 'actif',
        ]);

        $client = Client::withoutGlobalScopes()->where('nom', 'Client Nouveau')->firstOrFail();
        $response->assertRedirect(route('agence.clients.show', $client));
        $this->assertSame($agencyA->id, $client->agence_id);

        $this->actingAs($adminA)->put(route('agence.clients.update', $client), [
            'nom' => 'Client Modifié', 'telephone' => '06 00 00 00 00', 'statut' => 'inactif',
        ])->assertRedirect(route('agence.clients.show', $client));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'nom' => 'Client Modifié', 'statut' => 'inactif']);

        $this->actingAs($adminA)->get(route('agence.clients.show', $foreignClient))->assertForbidden();
        $this->actingAs($adminA)->get(route('agence.clients.edit', $foreignClient))->assertForbidden();
        $this->actingAs($adminA)->put(route('agence.clients.update', $foreignClient), ['nom' => 'Intrusion', 'telephone' => '0', 'statut' => 'actif'])->assertForbidden();
        $this->actingAs($adminA)->delete(route('agence.clients.destroy', $foreignClient))->assertForbidden();
        $this->assertDatabaseHas('clients', ['id' => $foreignClient->id, 'nom' => 'Client B']);
    }

    public function test_reservation_form_only_exposes_current_agency_clients_and_cars(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');
        $clientA = $this->createClient($agencyA, ['nom' => 'Client Visible']);
        $this->createClient($agencyB, ['nom' => 'Client Secret']);
        $this->createCar($agencyA, ['modele' => 'Voiture Visible', 'immatriculation' => 'AA-111-AA']);
        $this->createCar($agencyB, ['modele' => 'Voiture Secrète', 'immatriculation' => 'BB-111-BB']);

        $this->actingAs($adminA)
            ->get(route('agence.reservations.create', ['client_id' => $clientA->id]))
            ->assertOk()
            ->assertSee('Client Visible')
            ->assertSee('Voiture Visible')
            ->assertDontSee('Client Secret')
            ->assertDontSee('Voiture Secrète');
    }

    public function test_reservation_reference_and_amount_are_calculated_on_the_backend(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $client = $this->createClient($agency);
        $car = $this->createCar($agency, ['prix_jour' => 300]);

        $response = $this->actingAs($admin)->post(route('agence.reservations.store'), [
            'client_id' => $client->id,
            'voiture_id' => $car->id,
            'date_debut' => '2026-09-10',
            'date_fin' => '2026-09-12',
            'prix_jour' => 1,
            'montant' => 1,
            'statut' => 'confirmee',
        ]);

        $reservation = Reservation::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('agence.reservations.show', $reservation));
        $this->assertMatchesRegularExpression('/^RES-2026-\d{4}$/', $reservation->reference);
        $this->assertSame('300.00', $reservation->prix_jour);
        $this->assertSame('600.00', $reservation->montant);
        $this->assertSame($agency->id, $reservation->agence_id);
    }

    public function test_reservation_rejects_foreign_participants_and_overlapping_dates(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');
        $clientA = $this->createClient($agencyA);
        $clientB = $this->createClient($agencyB);
        $carA = $this->createCar($agencyA, ['immatriculation' => 'AA-222-AA']);
        $carB = $this->createCar($agencyB, ['immatriculation' => 'BB-222-BB']);
        $this->createReservation($agencyA, $clientA, $carA, ['date_debut' => '2026-09-10', 'date_fin' => '2026-09-12']);

        $payload = ['client_id' => $clientA->id, 'voiture_id' => $carA->id, 'date_debut' => '2026-09-12', 'date_fin' => '2026-09-14', 'statut' => 'confirmee'];
        $this->actingAs($adminA)->from(route('agence.reservations.create'))->post(route('agence.reservations.store'), $payload)
            ->assertRedirect(route('agence.reservations.create'))
            ->assertSessionHasErrors(['voiture_id' => 'Cette voiture est déjà réservée sur cette période.']);

        $this->actingAs($adminA)->post(route('agence.reservations.store'), array_merge($payload, ['client_id' => $clientB->id, 'voiture_id' => $carB->id]))->assertForbidden();
        $this->assertSame(1, Reservation::withoutGlobalScopes()->count());
    }

    public function test_cancelled_reservations_do_not_block_availability(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $client = $this->createClient($agency);
        $car = $this->createCar($agency);
        $this->createReservation($agency, $client, $car, ['date_debut' => '2026-09-10', 'date_fin' => '2026-09-12', 'statut' => 'annulee']);

        $this->actingAs($admin)->post(route('agence.reservations.store'), [
            'client_id' => $client->id, 'voiture_id' => $car->id, 'date_debut' => '2026-09-11', 'date_fin' => '2026-09-13', 'statut' => 'confirmee',
        ])->assertRedirect();

        $this->assertSame(2, Reservation::withoutGlobalScopes()->count());
    }

    public function test_reservations_are_scoped_searchable_filterable_paginated_and_foreign_actions_are_forbidden(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');
        $clientA = $this->createClient($agencyA, ['nom' => 'Yassine A']);
        $carA = $this->createCar($agencyA, ['immatriculation' => 'AA-333-AA']);
        foreach (range(1, 9) as $index) {
            $this->createReservation($agencyA, $clientA, $carA, ['reference' => sprintf('RES-2026-%04d', $index), 'date_debut' => "2026-10-{$index}", 'date_fin' => '2026-10-'.($index + 1), 'statut' => $index === 1 ? 'en_cours' : 'terminee']);
        }
        $clientB = $this->createClient($agencyB, ['nom' => 'Client B Secret']);
        $carB = $this->createCar($agencyB, ['immatriculation' => 'BB-333-BB']);
        $foreign = $this->createReservation($agencyB, $clientB, $carB, ['reference' => 'RES-2026-9999']);

        $this->actingAs($adminA)->get(route('agence.reservations.index'))->assertOk()->assertViewHas('reservations', fn ($items) => $items->total() === 9 && $items->count() === 8)->assertDontSee('Client B Secret');
        $this->actingAs($adminA)->get(route('agence.reservations.index', ['q' => 'RES-2026-0001', 'statut' => 'en_cours', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-02']))->assertOk()->assertSee('RES-2026-0001')->assertDontSee('RES-2026-0002');
        $this->actingAs($adminA)->get(route('agence.reservations.show', $foreign))->assertForbidden();
        $this->actingAs($adminA)->put(route('agence.reservations.update', $foreign), [])->assertForbidden();
        $this->actingAs($adminA)->patch(route('agence.reservations.annuler', $foreign))->assertForbidden();
    }

    public function test_contract_is_created_from_owned_reservation_with_copied_data_and_reference(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $client = $this->createClient($agency);
        $car = $this->createCar($agency);
        $reservation = $this->createReservation($agency, $client, $car, ['date_debut' => '2026-11-10', 'date_fin' => '2026-11-13', 'montant' => 900]);

        $response = $this->actingAs($admin)->post(route('agence.contrats.store'), ['reservation_id' => $reservation->id, 'statut' => 'actif', 'notes' => 'Contrat V1']);
        $contract = Contrat::withoutGlobalScopes()->firstOrFail();

        $response->assertRedirect(route('agence.contrats.show', $contract));
        $this->assertMatchesRegularExpression('/^CTR-2026-\d{4}$/', $contract->reference);
        $this->assertSame($agency->id, $contract->agence_id);
        $this->assertSame($reservation->id, $contract->reservation_id);
        $this->assertSame($client->id, $contract->client_id);
        $this->assertSame($car->id, $contract->voiture_id);
        $this->assertSame('900.00', $contract->montant);
    }

    public function test_contracts_are_scoped_filterable_and_foreign_actions_are_forbidden(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');
        $clientA = $this->createClient($agencyA);
        $carA = $this->createCar($agencyA, ['immatriculation' => 'AA-444-AA']);
        $reservationA = $this->createReservation($agencyA, $clientA, $carA);
        $contractA = $this->createContract($agencyA, $reservationA, ['reference' => 'CTR-2026-0044']);
        $clientB = $this->createClient($agencyB, ['nom' => 'Client B Secret']);
        $carB = $this->createCar($agencyB, ['immatriculation' => 'BB-444-BB']);
        $reservationB = $this->createReservation($agencyB, $clientB, $carB, ['reference' => 'RES-2026-8844']);
        $contractB = $this->createContract($agencyB, $reservationB, ['reference' => 'CTR-2026-9944']);

        $this->actingAs($adminA)->get(route('agence.contrats.index', ['q' => 'CTR-2026-0044', 'statut' => 'actif']))->assertOk()->assertSee($contractA->reference)->assertDontSee($contractB->reference)->assertDontSee('Client B Secret');
        $this->actingAs($adminA)->get(route('agence.contrats.show', $contractB))->assertForbidden();
        $this->actingAs($adminA)->get(route('agence.contrats.edit', $contractB))->assertForbidden();
        $this->actingAs($adminA)->put(route('agence.contrats.update', $contractB), [])->assertForbidden();
        $this->actingAs($adminA)->patch(route('agence.contrats.resilier', $contractB))->assertForbidden();
    }

    public function test_task_four_pages_render_and_super_admin_is_forbidden(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $client = $this->createClient($agency);
        $car = $this->createCar($agency);
        $reservation = $this->createReservation($agency, $client, $car);
        $contract = $this->createContract($agency, $reservation);

        foreach ([
            route('agence.clients.create'), route('agence.clients.show', $client), route('agence.clients.edit', $client),
            route('agence.reservations.create'), route('agence.reservations.show', $reservation), route('agence.reservations.edit', $reservation),
            route('agence.contrats.create'), route('agence.contrats.show', $contract), route('agence.contrats.edit', $contract),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $superAdmin = User::factory()->create(['agence_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->get(route('agence.clients.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('agence.reservations.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('agence.contrats.index'))->assertForbidden();
    }

    private function createAgencyAdmin(string $name): array
    {
        $agency = Agence::create(['nom' => $name, 'statut' => 'actif', 'date_expiration' => today()->addYear()]);
        $admin = User::factory()->create(['agence_id' => $agency->id, 'role' => User::ROLE_ADMIN_AGENCE]);

        return [$agency, $admin];
    }

    private function createClient(Agence $agency, array $attributes = []): Client
    {
        return Client::create(array_merge(['agence_id' => $agency->id, 'nom' => 'Client Test', 'telephone' => '06 10 20 30 40', 'email' => fake()->unique()->safeEmail(), 'ville' => 'Fès', 'statut' => 'actif'], $attributes));
    }

    private function createCar(Agence $agency, array $attributes = []): Voiture
    {
        return Voiture::create(array_merge(['agence_id' => $agency->id, 'marque' => 'Peugeot', 'modele' => '208', 'immatriculation' => fake()->unique()->bothify('??-###-??'), 'categorie' => 'Citadine', 'prix_jour' => 300, 'statut' => 'disponible'], $attributes));
    }

    private function createReservation(Agence $agency, Client $client, Voiture $car, array $attributes = []): Reservation
    {
        return Reservation::create(array_merge(['agence_id' => $agency->id, 'reference' => 'RES-2026-'.fake()->unique()->numerify('####'), 'client_id' => $client->id, 'voiture_id' => $car->id, 'date_debut' => '2026-12-10', 'date_fin' => '2026-12-12', 'prix_jour' => $car->prix_jour, 'montant' => 600, 'statut' => 'confirmee'], $attributes));
    }

    private function createContract(Agence $agency, Reservation $reservation, array $attributes = []): Contrat
    {
        return Contrat::create(array_merge(['agence_id' => $agency->id, 'reservation_id' => $reservation->id, 'client_id' => $reservation->client_id, 'voiture_id' => $reservation->voiture_id, 'reference' => 'CTR-2026-'.fake()->unique()->numerify('####'), 'date_debut' => $reservation->date_debut, 'date_fin' => $reservation->date_fin, 'montant' => $reservation->montant, 'statut' => 'actif'], $attributes));
    }
}
