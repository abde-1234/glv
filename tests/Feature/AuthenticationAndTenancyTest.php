<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('La gestion de')
            ->assertSee('location auto,')
            ->assertSee('simplement.')
            ->assertSee('Accédez à votre espace GLV')
            ->assertSee('Mot de passe oublié')
            ->assertSee('Une seule plateforme pour tous vos besoins.')
            ->assertSee('data-password-toggle', false);
    }

    public function test_super_admin_seeder_creates_a_hashed_password_and_no_agence(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $admin = User::where('email', 'admin@glv.test')->firstOrFail();

        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertNull($admin->agence_id);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_super_admin_can_log_in_and_reaches_its_dashboard(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $this->post('/login', [
            'email' => 'admin@glv.test',
            'password' => 'password',
        ])->assertRedirect('/super-admin/dashboard');

        $this->assertAuthenticated();
        $this->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee('Tableau de bord Super Admin');
    }

    public function test_admin_agence_can_log_in_and_reaches_agence_dashboard(): void
    {
        [$agence, $admin] = $this->createAgenceAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Agence')
            ->assertSee($agence->nom);
    }

    public function test_admin_agence_cannot_open_super_admin_dashboard(): void
    {
        [, $admin] = $this->createAgenceAdmin();

        $this->actingAs($admin)
            ->get('/super-admin/dashboard')
            ->assertForbidden();
    }

    public function test_super_admin_cannot_open_agence_dashboard(): void
    {
        $admin = User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_admin_agence_requires_an_agence(): void
    {
        $admin = User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_ADMIN_AGENCE,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_suspended_agence_is_blocked(): void
    {
        [, $admin] = $this->createAgenceAdmin(['statut' => 'suspendu']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('agence.settings.subscription'))
            ->assertSessionHas('warning', 'Votre abonnement doit être actif pour accéder à cette fonctionnalité.');
    }

    public function test_expired_agence_is_blocked(): void
    {
        [, $admin] = $this->createAgenceAdmin([
            'statut' => 'actif',
            'date_expiration' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('agence.settings.subscription'))
            ->assertSessionHas('warning', 'Votre abonnement doit être actif pour accéder à cette fonctionnalité.');
    }

    public function test_admin_agence_queries_are_automatically_isolated(): void
    {
        [$agence, $admin] = $this->createAgenceAdmin();
        $otherAgence = Agence::create(['nom' => 'Agence B', 'statut' => 'actif']);

        Client::create(['agence_id' => $agence->id, 'nom' => 'Client A']);
        Client::create(['agence_id' => $otherAgence->id, 'nom' => 'Client B']);

        $this->actingAs($admin);

        $this->assertSame(1, Client::count());
        $this->assertSame('Client A', Client::firstOrFail()->nom);

        $client = Client::create(['agence_id' => $otherAgence->id, 'nom' => 'Client C']);
        $this->assertSame($agence->id, $client->agence_id);
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $admin = User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_remember_me_persists_the_login_token(): void
    {
        $admin = User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
            'remember_token' => null,
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect('/super-admin/dashboard');

        $this->assertNotNull($admin->refresh()->remember_token);
    }

    private function createAgenceAdmin(array $agenceAttributes = []): array
    {
        $agence = Agence::create(array_merge([
            'nom' => 'Agence Casablanca',
            'statut' => 'actif',
        ], $agenceAttributes));

        $admin = User::factory()->create([
            'agence_id' => $agence->id,
            'role' => User::ROLE_ADMIN_AGENCE,
            'password' => 'password',
        ]);

        return [$agence, $admin];
    }
}
