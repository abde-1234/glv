<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_real_database_kpis(): void
    {
        $admin = $this->superAdmin();
        [$agence] = $this->agencyWithManager(['statut' => 'actif', 'date_expiration' => today()->addMonth()]);
        $trial = Agence::create(['nom' => 'Agence Essai', 'statut' => 'essai']);
        $car = Voiture::create([
            'agence_id' => $agence->id,
            'marque' => 'Renault',
            'modele' => 'Clio',
            'immatriculation' => '123-A-45',
            'statut' => 'disponible',
        ]);
        $client = Client::create(['agence_id' => $agence->id, 'nom' => 'Client Test']);
        Reservation::create([
            'agence_id' => $agence->id,
            'client_id' => $client->id,
            'voiture_id' => $car->id,
            'date_debut' => today(),
            'date_fin' => today()->addDay(),
            'statut' => 'confirmee',
        ]);

        $this->actingAs($admin)
            ->get('/super-admin/dashboard')
            ->assertOk()
            ->assertViewHas('activeAgencies', 1)
            ->assertViewHas('totalCars', 1)
            ->assertViewHas('totalReservations', 1)
            ->assertViewHas('activeSubscriptions', 1)
            ->assertSee($trial->nom);
    }

    public function test_admin_agence_is_forbidden_from_all_super_admin_sections(): void
    {
        [, $manager] = $this->agencyWithManager();

        foreach (['/super-admin/dashboard', '/super-admin/agences', '/super-admin/agences/create', '/super-admin/abonnements'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
    }

    public function test_agency_list_supports_search_status_city_and_pagination(): void
    {
        $admin = $this->superAdmin();
        [$target] = $this->agencyWithManager([
            'nom' => 'Atlas Location',
            'ville' => 'Casablanca',
            'statut' => 'actif',
        ]);

        foreach (range(1, 11) as $index) {
            Agence::create(['nom' => "Agence {$index}", 'statut' => 'essai']);
        }

        $this->actingAs($admin)
            ->get('/super-admin/agences')
            ->assertOk()
            ->assertViewHas('agences', fn ($agences): bool => $agences instanceof LengthAwarePaginator && $agences->perPage() === 10 && $agences->total() === 12);

        $this->actingAs($admin)
            ->get('/super-admin/agences?q=Atlas&statut=actif&ville=Casablanca')
            ->assertOk()
            ->assertSee($target->nom)
            ->assertDontSee('Agence 1</strong>', false);
    }

    public function test_creating_agency_also_creates_hashed_admin_agence_in_transaction(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/super-admin/agences', $this->validAgencyPayload());

        $agence = Agence::where('email', 'contact@nova.test')->firstOrFail();
        $manager = User::where('email', 'gerant@nova.test')->firstOrFail();

        $response->assertRedirect(route('super-admin.agences.show', $agence));
        $this->assertSame($agence->id, $manager->agence_id);
        $this->assertSame(User::ROLE_ADMIN_AGENCE, $manager->role);
        $this->assertSame('06 98 76 54 32', $manager->telephone);
        $this->assertTrue(Hash::check('temporary-password', $manager->password));
    }

    public function test_agency_and_manager_can_be_updated_without_changing_password(): void
    {
        $admin = $this->superAdmin();
        [$agence, $manager] = $this->agencyWithManager();
        $password = $manager->password;

        $payload = $this->validAgencyPayload([
            'nom' => 'Agence Modifiée',
            'gerant_email' => $manager->email,
            'gerant_password' => '',
        ]);

        $this->actingAs($admin)
            ->put("/super-admin/agences/{$agence->id}", $payload)
            ->assertRedirect(route('super-admin.agences.show', $agence));

        $this->assertSame('Agence Modifiée', $agence->fresh()->nom);
        $this->assertSame($password, $manager->fresh()->password);
        $this->assertSame('Gérant Nova', $manager->fresh()->name);
    }

    public function test_agency_create_edit_and_show_pages_render(): void
    {
        $admin = $this->superAdmin();
        [$agence] = $this->agencyWithManager();

        $this->actingAs($admin)->get('/super-admin/agences/create')->assertOk()->assertSee('Informations générales');
        $this->actingAs($admin)->get("/super-admin/agences/{$agence->id}")->assertOk()->assertSee($agence->nom);
        $this->actingAs($admin)->get("/super-admin/agences/{$agence->id}/edit")->assertOk()->assertSee('Enregistrer les modifications');
    }

    public function test_agency_can_be_suspended_then_activated_and_suspended_manager_is_blocked(): void
    {
        $admin = $this->superAdmin();
        [$agence, $manager] = $this->agencyWithManager();

        $this->actingAs($admin)
            ->patch("/super-admin/agences/{$agence->id}/suspendre")
            ->assertSessionHas('success', 'Agence suspendue avec succès.');

        $this->assertSame('suspendu', $agence->fresh()->statut);
        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertRedirect(route('agence.settings.subscription'));

        $this->actingAs($admin)
            ->patch("/super-admin/agences/{$agence->id}/activer")
            ->assertSessionHas('success', 'Agence activée avec succès.');

        $this->assertSame('actif', $agence->fresh()->statut);
    }

    public function test_subscription_page_displays_expiration_and_allows_manual_update(): void
    {
        $admin = $this->superAdmin();
        [$agence] = $this->agencyWithManager([
            'type_abonnement' => 'Basic',
            'date_expiration' => today()->addDays(12),
        ]);

        $this->actingAs($admin)
            ->get("/super-admin/abonnements?edit={$agence->id}")
            ->assertOk()
            ->assertSee('12 jours')
            ->assertSee($agence->date_expiration->format('d/m/Y'));

        $this->actingAs($admin)
            ->patch("/super-admin/abonnements/{$agence->id}", [
                'type_abonnement' => 'Premium',
                'date_expiration' => today()->addYear()->format('Y-m-d'),
                'statut' => 'actif',
            ])
            ->assertSessionHas('success', 'Abonnement modifié avec succès.');

        $this->assertSame('Premium', $agence->fresh()->type_abonnement);
    }

    public function test_agency_validation_rejects_duplicate_manager_email_and_invalid_logo(): void
    {
        $admin = $this->superAdmin();
        [, $existingManager] = $this->agencyWithManager();

        $this->actingAs($admin)
            ->post('/super-admin/agences', $this->validAgencyPayload([
                'gerant_email' => $existingManager->email,
                'statut' => 'invalide',
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['gerant_email', 'statut', 'logo']);

        $this->assertDatabaseMissing('agences', ['email' => 'contact@nova.test']);
    }

    public function test_agency_deletion_removes_its_manager_after_confirmation_request(): void
    {
        $admin = $this->superAdmin();
        [$agence, $manager] = $this->agencyWithManager();

        $this->actingAs($admin)
            ->delete("/super-admin/agences/{$agence->id}")
            ->assertRedirect('/super-admin/agences')
            ->assertSessionHas('success', 'Agence supprimée avec succès.');

        $this->assertDatabaseMissing('agences', ['id' => $agence->id]);
        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
    }

    public function test_replacing_agency_logo_removes_only_its_previous_file(): void
    {
        Storage::fake('public');
        [$agency] = $this->agencyWithManager(['logo' => 'agences/logos/old.png']);
        Storage::disk('public')->put('agences/logos/old.png', 'old');
        Storage::disk('public')->put('agences/logos/other.png', 'other');
        $logo = UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->actingAs($this->superAdmin())->put('/super-admin/agences/'.$agency->id, $this->validAgencyPayload(['logo' => $logo]))
            ->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('agences/logos/old.png');
        Storage::disk('public')->assertExists('agences/logos/other.png');
        Storage::disk('public')->assertExists($agency->fresh()->logo);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    private function agencyWithManager(array $attributes = []): array
    {
        $agence = Agence::create(array_merge([
            'nom' => 'Agence Test',
            'email' => fake()->unique()->companyEmail(),
            'telephone' => '06 11 22 33 44',
            'ville' => 'Rabat',
            'statut' => 'actif',
            'type_abonnement' => 'Pro',
            'date_expiration' => today()->addMonths(3),
        ], $attributes));

        $manager = User::factory()->create([
            'agence_id' => $agence->id,
            'role' => User::ROLE_ADMIN_AGENCE,
            'telephone' => '06 44 33 22 11',
        ]);

        return [$agence, $manager];
    }

    private function validAgencyPayload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Nova Rent',
            'email' => 'contact@nova.test',
            'telephone' => '06 12 34 56 78',
            'ville' => 'Tanger',
            'adresse' => '10 avenue principale',
            'statut' => 'essai',
            'type_abonnement' => 'Pro',
            'date_expiration' => today()->addMonth()->format('Y-m-d'),
            'gerant_name' => 'Gérant Nova',
            'gerant_email' => 'gerant@nova.test',
            'gerant_telephone' => '06 98 76 54 32',
            'gerant_password' => 'temporary-password',
        ], $overrides);
    }
}
