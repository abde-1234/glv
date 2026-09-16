<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgenceSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_dashboard_uses_only_the_authenticated_agency_data(): void
    {
        [$agence, $admin] = $this->createAgenceAdmin('Agence A');
        [$otherAgence] = $this->createAgenceAdmin('Agence B');

        $car = $this->createCar($agence, ['immatriculation' => 'AA-101-AA']);
        $this->createCar($agence, ['immatriculation' => 'AA-102-AA', 'statut' => 'maintenance']);
        $this->createCar($otherAgence, ['immatriculation' => 'BB-101-BB']);

        $client = Client::create(['agence_id' => $agence->id, 'nom' => 'Yassine El Amrani']);
        Client::create(['agence_id' => $otherAgence->id, 'nom' => 'Client externe']);
        Reservation::create([
            'agence_id' => $agence->id,
            'client_id' => $client->id,
            'voiture_id' => $car->id,
            'date_debut' => today()->subDays(2),
            'date_fin' => today()->addDay(),
            'montant' => 900,
            'statut' => 'confirmee',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalVoitures', 2)
            ->assertViewHas('totalClients', 1)
            ->assertViewHas('totalReservations', 1)
            ->assertViewHas('chiffreAffaires', 900.0)
            ->assertSee('Bonjour')
            ->assertSee('Réservations des 30 derniers jours');
    }

    public function test_vehicle_list_is_searchable_filterable_and_tenant_scoped(): void
    {
        [$agence, $admin] = $this->createAgenceAdmin('Agence A');
        [$otherAgence] = $this->createAgenceAdmin('Agence B');

        $this->createCar($agence, ['marque' => 'Peugeot', 'modele' => '208', 'immatriculation' => 'AA-208-AA', 'categorie' => 'Citadine']);
        $this->createCar($agence, ['marque' => 'Dacia', 'modele' => 'Duster', 'immatriculation' => 'AA-404-AA', 'categorie' => 'SUV', 'statut' => 'maintenance']);
        $this->createCar($otherAgence, ['marque' => 'Toyota', 'modele' => 'Corolla', 'immatriculation' => 'BB-999-BB']);

        $this->actingAs($admin)
            ->get(route('agence.voitures.index', ['q' => 'Duster', 'statut' => 'maintenance', 'categorie' => 'SUV']))
            ->assertOk()
            ->assertSee('Dacia Duster')
            ->assertDontSee('Peugeot 208')
            ->assertDontSee('Toyota Corolla')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 2 && $stats['maintenance'] === 1);
    }

    public function test_vehicle_create_edit_and_detail_pages_render(): void
    {
        [$agence, $admin] = $this->createAgenceAdmin('Agence A');
        $voiture = $this->createCar($agence, ['immatriculation' => 'AA-208-AA']);

        $this->actingAs($admin)
            ->get(route('agence.voitures.create'))
            ->assertOk()
            ->assertSee('Ajouter une voiture')
            ->assertSee('Photo du véhicule');

        $this->actingAs($admin)
            ->get(route('agence.voitures.edit', $voiture))
            ->assertOk()
            ->assertSee('Modifier la voiture')
            ->assertSee('AA-208-AA');

        $this->actingAs($admin)
            ->get(route('agence.voitures.show', $voiture))
            ->assertOk()
            ->assertSee('Peugeot 208')
            ->assertSee('AA-208-AA');
    }

    public function test_admin_can_create_a_vehicle_with_a_photo_for_own_agency(): void
    {
        Storage::fake('public');
        [$agence, $admin] = $this->createAgenceAdmin('Agence A');
        [$otherAgence] = $this->createAgenceAdmin('Agence B');

        $response = $this->actingAs($admin)->post(route('agence.voitures.store'), [
            'agence_id' => $otherAgence->id,
            'marque' => 'Peugeot',
            'modele' => '208',
            'immatriculation' => ' pe-123-ab ',
            'categorie' => 'Citadine',
            'annee' => 2024,
            'prix_jour' => 300,
            'kilometrage' => 45000,
            'statut' => 'disponible',
            'photo' => $this->uploadedPng('peugeot.png'),
        ]);

        $voiture = Voiture::withoutGlobalScopes()->where('immatriculation', 'PE-123-AB')->firstOrFail();

        $response->assertRedirect(route('agence.voitures.show', $voiture));
        $this->assertSame($agence->id, $voiture->agence_id);
        Storage::disk('public')->assertExists($voiture->photo);
    }

    public function test_admin_can_update_and_delete_an_owned_vehicle(): void
    {
        Storage::fake('public');
        [$agence, $admin] = $this->createAgenceAdmin('Agence A');
        $voiture = $this->createCar($agence, ['immatriculation' => 'AA-123-AA', 'photo' => 'voitures/old.jpg']);
        Storage::disk('public')->put('voitures/old.jpg', 'old-photo');

        $this->actingAs($admin)
            ->put(route('agence.voitures.update', $voiture), [
                'marque' => 'Renault',
                'modele' => 'Clio V',
                'immatriculation' => 'AA-123-AA',
                'categorie' => 'Citadine',
                'annee' => 2025,
                'prix_jour' => 350,
                'kilometrage' => 12000,
                'statut' => 'loue',
                'photo' => $this->uploadedPng('clio.png'),
            ])
            ->assertRedirect(route('agence.voitures.show', $voiture));

        $voiture->refresh();
        $this->assertSame('Clio V', $voiture->modele);
        $this->assertSame('loue', $voiture->statut);
        Storage::disk('public')->assertMissing('voitures/old.jpg');
        Storage::disk('public')->assertExists($voiture->photo);

        $photo = $voiture->photo;
        $this->actingAs($admin)
            ->delete(route('agence.voitures.destroy', $voiture))
            ->assertRedirect(route('agence.voitures.index'));

        $this->assertDatabaseMissing('voitures', ['id' => $voiture->id]);
        Storage::disk('public')->assertMissing($photo);
    }

    public function test_admin_cannot_access_or_mutate_another_agency_vehicle(): void
    {
        [, $admin] = $this->createAgenceAdmin('Agence A');
        [$otherAgence] = $this->createAgenceAdmin('Agence B');
        $foreignCar = $this->createCar($otherAgence, ['immatriculation' => 'BB-123-BB']);

        $this->actingAs($admin)
            ->get(route('agence.voitures.show', $foreignCar))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('agence.voitures.update', $foreignCar), [
                'marque' => 'Intrusion',
                'modele' => 'Refusée',
                'immatriculation' => 'BB-123-BB',
                'statut' => 'disponible',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('agence.voitures.destroy', $foreignCar))
            ->assertNotFound();

        $this->assertDatabaseHas('voitures', ['id' => $foreignCar->id, 'marque' => 'Peugeot']);
    }

    public function test_super_admin_cannot_access_agency_vehicle_pages(): void
    {
        $superAdmin = User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('agence.voitures.index'))
            ->assertForbidden();
    }

    private function createAgenceAdmin(string $name): array
    {
        $agence = Agence::create([
            'nom' => $name,
            'statut' => 'actif',
            'date_expiration' => today()->addYear(),
        ]);

        $admin = User::factory()->create([
            'agence_id' => $agence->id,
            'role' => User::ROLE_ADMIN_AGENCE,
        ]);

        return [$agence, $admin];
    }

    private function createCar(Agence $agence, array $attributes = []): Voiture
    {
        return Voiture::create(array_merge([
            'agence_id' => $agence->id,
            'marque' => 'Peugeot',
            'modele' => '208',
            'immatriculation' => 'PE-'.fake()->unique()->numberBetween(100, 999).'-AB',
            'categorie' => 'Citadine',
            'annee' => 2024,
            'prix_jour' => 300,
            'kilometrage' => 45000,
            'statut' => 'disponible',
        ], $attributes));
    }

    private function uploadedPng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
