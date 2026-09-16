<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_expired_and_suspended_agencies_cannot_write_or_generate_pdf_but_can_reach_account(): void
    {
        foreach (['expire', 'suspendu'] as $status) {
            $a = $this->tenant($status, ['statut' => $status]);
            $this->actingAs($a['admin']);
            foreach ([['POST', '/voitures'], ['PUT', '/voitures/'.$a['car']->id], ['DELETE', '/clients/'.$a['client']->id],
                ['POST', '/reservations'], ['PATCH', '/reservations/'.$a['reservation']->id.'/annuler'],
                ['POST', '/contrats'], ['PATCH', '/contrats/'.$a['contract']->id.'/resilier'],
                ['GET', '/contrats/'.$a['contract']->id.'/pdf'], ['GET', '/contrats/'.$a['contract']->id.'/pdf/preview']] as [$method, $url]) {
                $this->call($method, $url)->assertRedirect('/parametres/abonnement');
            }
            $this->get('/parametres/abonnement')->assertOk()->assertSee($status === 'expire' ? 'Abonnement expiré' : 'Agence suspendue')
                ->assertSee('Mon abonnement')->assertSee('Mon profil')->assertSee('Se déconnecter')->assertSee('Contacter l’administrateur');
            $this->get('/profil')->assertOk();
            $this->assertDatabaseHas('clients', ['id' => $a['client']->id]);
            $this->assertDatabaseHas('reservations', ['id' => $a['reservation']->id, 'statut' => 'confirmee']);
            $this->assertDatabaseHas('contrats', ['id' => $a['contract']->id, 'statut' => 'actif']);
            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_five_subscription_states_and_expiration_day_boundary(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 14)->startOfDay());
        foreach ([['A', 'actif', 30, true], ['B', 'essai', 12, true], ['C', 'actif', 5, true],
            ['D', 'actif', -1, false], ['E', 'suspendu', 30, false], ['Today', 'actif', 0, true]] as [$name, $status, $days, $allowed]) {
            $a = $this->tenant($name, ['statut' => $status, 'date_expiration' => today()->addDays($days)]);
            $response = $this->actingAs($a['admin'])->get('/dashboard');
            if ($allowed) {
                $response->assertOk();
            } else {
                $response->assertRedirect('/parametres/abonnement');
            }
            if ($name === 'C') {
                $response->assertSee('Votre abonnement expire dans 5 jour(s).');
            }
        }
        $this->travelBack();
    }
}
