<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_all_foreign_resource_actions_are_blocked_in_both_directions(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        foreach ([[$a, $b], [$b, $a]] as [$own, $foreign]) {
            $this->actingAs($own['admin']);
            foreach (['voitures' => 'car', 'clients' => 'client', 'reservations' => 'reservation', 'contrats' => 'contract'] as $module => $key) {
                $id = $foreign[$key]->id;
                foreach ([['GET', "/$module/$id"], ['GET', "/$module/$id/edit"], ['PUT', "/$module/$id"], ['DELETE', "/$module/$id"]] as [$method, $url]) {
                    $response = $this->call($method, $url, ['agence_id' => $own['agency']->id]);
                    $this->assertContains($response->status(), [403, 404], "$method $url");
                }
                $this->get("/$module")->assertOk()->assertDontSee($foreign[$key]->reference ?? $foreign[$key]->immatriculation ?? $foreign[$key]->nom);
            }
            $this->patch('/reservations/'.$foreign['reservation']->id.'/annuler')->assertForbidden();
            $this->get('/reservations/'.$foreign['reservation']->id.'/contrat/create')->assertForbidden();
            $this->patch('/contrats/'.$foreign['contract']->id.'/resilier')->assertForbidden();
            foreach (['pdf', 'pdf/preview'] as $suffix) {
                $this->get('/contrats/'.$foreign['contract']->id.'/'.$suffix)->assertForbidden();
            }
            $this->get('/parametres/documents/'.$foreign['document']->id.'/telecharger')->assertForbidden();
            $this->delete('/parametres/documents/'.$foreign['document']->id)->assertForbidden();
            $this->put('/parametres/utilisateurs/'.$foreign['employee']->id, [])->assertForbidden();
            $this->delete('/parametres/utilisateurs/'.$foreign['employee']->id)->assertForbidden();
            $this->assertDatabaseHas('contrats', ['id' => $foreign['contract']->id, 'statut' => 'actif']);
            $this->assertDatabaseHas('reservations', ['id' => $foreign['reservation']->id, 'statut' => 'confirmee']);
        }
    }

    public function test_every_super_admin_route_rejects_agency_roles(): void
    {
        $tenant = $this->tenant('A');
        foreach ([$tenant['admin'], $tenant['employee']] as $user) {
            $this->actingAs($user);
            foreach (Route::getRoutes() as $route) {
                if (! str_starts_with($route->uri(), 'super-admin/')) {
                    continue;
                }
                $uri = '/'.str_replace('{agence}', $tenant['agency']->id, $route->uri());
                $this->call($route->methods()[0], $uri)->assertForbidden();
            }
        }
    }

    public function test_protected_fields_cannot_be_injected_into_profile_vehicle_client_or_settings(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $injection = ['agence_id' => $b['agency']->id, 'role' => User::ROLE_SUPER_ADMIN, 'statut' => 'suspendu'];
        $this->actingAs($a['admin'])->put('/profil', $injection + ['name' => 'Profil A', 'email' => 'new@a.test'])->assertSessionHasNoErrors();
        $this->put('/parametres', $injection + ['nom' => 'Agence A modifiée', 'type_abonnement' => 'Pirate', 'date_expiration' => '2099-01-01'])->assertSessionHasNoErrors();
        $this->put('/clients/'.$a['client']->id, ['nom' => 'Client A modifié', 'telephone' => '0611223344', 'statut' => 'actif'] + $injection)->assertSessionHasNoErrors();
        $this->put('/voitures/'.$a['car']->id, ['marque' => 'Peugeot', 'modele' => '208', 'immatriculation' => 'IMM-A', 'statut' => 'disponible'] + $injection)->assertSessionHasNoErrors();
        foreach (['admin', 'client', 'car'] as $key) {
            $this->assertSame($a['agency']->id, $a[$key]->fresh()->agence_id);
        }
        $this->assertSame(User::ROLE_ADMIN_AGENCE, $a['admin']->fresh()->role);
        $this->assertSame('actif', $a['admin']->fresh()->statut);
        $this->assertSame('Pro', $a['agency']->fresh()->type_abonnement);
        $this->assertSame('actif', $a['agency']->fresh()->statut);
    }

    public function test_unknown_ids_have_clean_404_pages_and_forbidden_pages_do_not_leak_details(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $this->actingAs($a['admin']);
        foreach (['voitures', 'clients', 'reservations', 'contrats'] as $module) {
            $this->get("/$module/999999")->assertNotFound()->assertSee('Page introuvable')->assertDontSee('SQLSTATE');
        }
        $this->get('/clients/'.$b['client']->id)->assertForbidden()->assertSee('Accès refusé')->assertDontSee($b['client']->nom);
    }

    public function test_sql_payload_and_stored_xss_do_not_escape_the_agency_or_html(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $payload = '<script>alert("GLV")</script>';
        $a['client']->update(['nom' => $payload, 'notes' => $payload]);
        $a['agency']->update(['description' => $payload]);
        $a['employee']->update(['name' => $payload]);
        $a['contract']->update(['notes' => $payload]);
        $this->actingAs($a['admin']);
        foreach (['/clients/'.$a['client']->id, '/contrats/'.$a['contract']->id, '/parametres', '/parametres/utilisateurs'] as $url) {
            $this->get($url)->assertOk()->assertSee(e($payload), false)->assertDontSee($payload, false);
        }
        foreach (['voitures', 'clients', 'reservations', 'contrats'] as $module) {
            $this->get('/'.$module.'?'.http_build_query(['q' => "%' OR 1=1 --"]))->assertOk()->assertDontSee($b['client']->nom)->assertDontSee($b['car']->immatriculation);
        }
    }
}
