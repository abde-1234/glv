<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GlvWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_to_agency_contract_pdf_and_logout_workflow(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'agence_id' => null]);
        $this->post('/login', ['email' => $super->email, 'password' => 'password'])->assertRedirect('/super-admin/dashboard');
        $this->post('/super-admin/agences', [
            'nom' => 'Agence Workflow', 'email' => 'agence@workflow.test', 'telephone' => '0611223344', 'ville' => 'Fès',
            'statut' => 'essai', 'type_abonnement' => 'Pro', 'date_expiration' => today()->addDays(12)->format('Y-m-d'),
            'gerant_name' => 'Admin Workflow', 'gerant_email' => 'admin@workflow.test', 'gerant_telephone' => '0611223344',
            'gerant_password' => 'workflow-password', 'agence_id' => 1234, 'role' => 'super_admin',
        ])->assertSessionHasNoErrors();
        $agency = Agence::firstOrFail();
        $admin = User::where('email', 'admin@workflow.test')->firstOrFail();
        $this->assertNull($super->fresh()->agence_id);
        $this->assertSame(User::ROLE_ADMIN_AGENCE, $admin->role);
        $this->patch('/super-admin/abonnements/'.$agency->id, ['statut' => 'actif', 'type_abonnement' => 'Pro',
            'date_debut_abonnement' => today()->format('Y-m-d'), 'date_expiration' => today()->addYear()->format('Y-m-d'),
            'montant_abonnement' => 299])->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'workflow-password'])->assertRedirect('/dashboard');
        $this->post('/voitures', ['marque' => 'Peugeot', 'modele' => '208', 'immatriculation' => 'AA-123-BB',
            'prix_jour' => 300, 'statut' => 'disponible'])->assertSessionHasNoErrors();
        $car = Voiture::firstOrFail();
        $this->post('/clients', ['nom' => 'Client Workflow', 'telephone' => '0611223344', 'statut' => 'actif'])->assertSessionHasNoErrors();
        $client = Client::firstOrFail();
        $this->post('/reservations', ['client_id' => $client->id, 'voiture_id' => $car->id,
            'date_debut' => '2026-12-10', 'date_fin' => '2026-12-12', 'statut' => 'confirmee'])->assertSessionHasNoErrors();
        $reservation = Reservation::firstOrFail();
        $this->post('/contrats', ['reservation_id' => $reservation->id, 'statut' => 'actif'])->assertSessionHasNoErrors();
        $contract = Contrat::firstOrFail();
        $this->get('/contrats/'.$contract->id)->assertOk()->assertSee('Télécharger PDF')->assertSee('Aperçu PDF');
        foreach (['pdf', 'pdf/preview'] as $suffix) {
            $this->get('/contrats/'.$contract->id.'/'.$suffix)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->put('/profil', ['name' => 'Admin mis à jour', 'email' => $admin->email])->assertSessionHasNoErrors();
        $this->get('/parametres/abonnement')->assertOk()->assertSee('299,00 MAD');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
