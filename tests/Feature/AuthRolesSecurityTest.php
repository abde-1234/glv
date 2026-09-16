<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class AuthRolesSecurityTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_guest_access_invalid_login_and_throttling(): void
    {
        foreach (['/dashboard', '/clients', '/voitures', '/reservations', '/contrats/1/pdf', '/contrats/1/pdf/preview', '/parametres/documents/1/telecharger', '/super-admin/dashboard'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $a = $this->tenant('A');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $a['admin']->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->post('/login', ['email' => $a['admin']->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_sessions_and_super_admin_with_tenant_are_denied(): void
    {
        $a = $this->tenant('A');
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'agence_id' => null, 'statut' => 'inactif']);
        $this->actingAs($super)->get('/super-admin/dashboard')->assertForbidden();
        $super->update(['statut' => 'actif', 'agence_id' => $a['agency']->id]);
        $this->actingAs($super)->get('/super-admin/dashboard')->assertForbidden();
        $a['admin']->update(['statut' => 'inactif']);
        $this->actingAs($a['admin'])->get('/dashboard')->assertForbidden();
        $this->get('/parametres/utilisateurs')->assertForbidden();
    }

    public function test_employees_cannot_escalate_or_manage_users_and_admin_cannot_disable_self(): void
    {
        $a = $this->tenant('A');
        $this->actingAs($a['employee'])->post('/parametres/utilisateurs', [])->assertForbidden();
        $this->put('/parametres/utilisateurs/'.$a['admin']->id, [])->assertForbidden();
        $this->delete('/parametres/utilisateurs/'.$a['admin']->id)->assertForbidden();
        $this->actingAs($a['admin'])->postJson('/parametres/utilisateurs', [
            'name' => 'Intrus', 'email' => 'intrus@test.test', 'password' => 'password-valid', 'role' => 'super_admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->putJson('/parametres/utilisateurs/'.$a['admin']->id, [
            'name' => $a['admin']->name, 'email' => $a['admin']->email, 'role' => 'employe', 'statut' => 'inactif',
        ])->assertUnprocessable();
        $this->deleteJson('/parametres/utilisateurs/'.$a['admin']->id)->assertUnprocessable();
    }

    public function test_password_change_requires_confirmation_and_old_password_stops_working(): void
    {
        $a = $this->tenant('A');
        $this->actingAs($a['admin']);
        $this->putJson('/profil/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'mismatch'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->put('/profil/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $a['admin']->fresh()->password));
        $this->assertFalse(Hash::check('password', $a['admin']->fresh()->password));
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $a['admin']->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $a['admin']->email, 'password' => 'new-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($a['admin']);
    }

    public function test_csrf_is_required_for_state_changes_in_the_real_web_middleware(): void
    {
        $a = $this->tenant('A');
        $this->actingAs($a['admin']);
        // Only disable Laravel's testing-mode CSRF bypass; the DB stays SQLite in memory.
        $this->app->instance('env', 'local');
        try {
            foreach ([['POST', '/clients'], ['PUT', '/profil'], ['PATCH', '/contrats/'.$a['contract']->id.'/resilier'], ['DELETE', '/clients/'.$a['client']->id]] as [$method, $url]) {
                $this->call($method, $url)->assertStatus(419);
            }
            $this->withSession(['_token' => 'test-csrf-token'])->post('/clients', [
                '_token' => 'test-csrf-token', 'nom' => 'Client CSRF', 'telephone' => '0611223344', 'statut' => 'actif',
            ])->assertSessionHasNoErrors()->assertRedirect();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_demo_seeder_never_resets_existing_password_or_runs_in_production(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $user = User::where('email', 'admin@glv.test')->firstOrFail();
        $user->update(['password' => 'changed-password']);
        $this->seed(SuperAdminSeeder::class);
        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->app->instance('env', 'production');
        try {
            $this->expectException(\RuntimeException::class);
            (new SuperAdminSeeder)->run();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
