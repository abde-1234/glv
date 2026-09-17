<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\RenewalRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Voiture;
use App\Notifications\GlvNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class FinalQaWorkflowTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public static function agencies(): array
    {
        return [
            'Atlas Rent' => ['Atlas Rent', 'Pro', 'actif'],
            'Rif Car' => ['Rif Car', 'Basic', 'actif'],
            'Ocean Cars' => ['Ocean Cars', 'Premium', 'essai'],
        ];
    }

    #[DataProvider('agencies')]
    public function test_complete_http_workflow_through_expiration_and_restored_access(string $name, string $plan, string $status): void
    {
        $this->travelTo(now()->setDate(2026, 9, 17)->setTime(12, 0));
        Storage::fake('public');
        $foreign = $this->tenant('FOREIGN-QA');
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'agence_id' => null]);
        $this->post('/login', ['email' => $super->email, 'password' => 'password'])->assertRedirect('/super-admin/dashboard');
        $this->get('/super-admin/dashboard')->assertOk();
        $this->post('/super-admin/agences', [
            'nom' => $name, 'email' => 'contact@final-qa.test', 'telephone' => '0611223344', 'ville' => 'Fès',
            'statut' => $status, 'type_abonnement' => $plan, 'date_expiration' => today()->addDays(30)->toDateString(),
            'logo' => UploadedFile::fake()->image('logo.png', 180, 70),
            'gerant_name' => 'Admin '.$name, 'gerant_email' => 'admin@final-qa.test', 'gerant_telephone' => '0611223344',
            'gerant_password' => 'final-qa-password',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $agency = Agence::where('nom', $name)->firstOrFail();
        $admin = User::where('email', 'admin@final-qa.test')->firstOrFail();
        $this->patch('/super-admin/abonnements/'.$agency->id, [
            'type_abonnement' => $plan, 'statut' => $status, 'date_debut_abonnement' => today()->toDateString(),
            'date_expiration' => today()->addDays(30)->toDateString(), 'montant_abonnement' => 999,
        ])->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'final-qa-password'])->assertRedirect('/dashboard');

        foreach (['/dashboard', '/voitures', '/clients', '/reservations', '/contrats', '/parametres', '/parametres/abonnement'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee($foreign['agency']->nom);
        }
        $injection = ['agence_id' => $foreign['agency']->id, 'agency_id' => $foreign['agency']->id,
            'subscription_id' => $foreign['agency']->id, 'requested_by' => $foreign['admin']->id, 'montant' => 1, 'status' => 'approved'];
        $this->post('/voitures', [
            'marque' => 'Peugeot', 'modele' => '208 QA', 'immatriculation' => 'FINAL-QA',
            'prix_jour' => 300, 'statut' => 'disponible', 'photo' => UploadedFile::fake()->image('car.png', 640, 360),
        ] + $injection)->assertSessionHasNoErrors();
        $car = Voiture::where('immatriculation', 'FINAL-QA')->firstOrFail();
        $this->post('/clients', ['nom' => 'Client QA '.$name, 'telephone' => '0611223344', 'statut' => 'actif'] + $injection)->assertSessionHasNoErrors();
        $client = Client::where('nom', 'Client QA '.$name)->firstOrFail();
        $this->post('/reservations', ['client_id' => $client->id, 'voiture_id' => $car->id,
            'date_debut' => '2026-10-01', 'date_fin' => '2026-10-03', 'statut' => 'confirmee'] + $injection)->assertSessionHasNoErrors();
        $reservation = Reservation::where('voiture_id', $car->id)->firstOrFail();
        $this->post('/contrats', ['reservation_id' => $reservation->id, 'statut' => 'actif',
            'client_id' => $foreign['client']->id, 'voiture_id' => $foreign['car']->id] + $injection)->assertSessionHasNoErrors();
        $contract = Contrat::where('reservation_id', $reservation->id)->firstOrFail();
        foreach ([$car, $client, $reservation, $contract] as $record) {
            $this->assertSame($agency->id, $record->agence_id);
        }
        $this->assertSame($client->id, $contract->client_id);
        $this->assertSame($car->id, $contract->voiture_id);
        $this->assertSame('600.00', $contract->montant);

        $pdfData = null;
        View::composer('agence.contrats.pdf', function ($view) use (&$pdfData): void { $pdfData = $view->getData(); });
        foreach (['pdf' => 'attachment', 'pdf/preview' => 'inline'] as $suffix => $disposition) {
            $pdf = $this->get('/contrats/'.$contract->id.'/'.$suffix)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString($disposition, $pdf->headers->get('Content-Disposition'));
            $this->assertSame(2, preg_match_all('~/Subtype\s*/Image\b~', $pdf->getContent()));
            $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $pdf->getContent()));
            $html = view('agence.contrats.pdf', $pdfData)->render();
            foreach ([$name, $client->nom, $car->immatriculation, $reservation->displayReference(), '600,00 MAD', '300,00 MAD', '01/10/2026', '03/10/2026', 'Signature et cachet'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertStringNotContainsString($foreign['agency']->nom, $html);
        }

        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->artisan('subscriptions:check')->expectsOutput('0 alerte(s) créée(s).')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->where('data->type', 'expiration_30_days')->count());
        $this->get('/parametres/abonnement')->assertOk()->assertSee('1 non lue(s)');
        $notice = $admin->notifications()->firstOrFail();
        $this->patch('/notifications/'.$notice->id.'/read')->assertRedirect('/parametres/abonnement');
        $this->assertNotNull($notice->fresh()->read_at);

        $this->travel(31)->days();
        foreach (['/dashboard', '/voitures', '/clients', '/reservations', '/contrats', '/contrats/'.$contract->id.'/pdf'] as $uri) {
            $this->get($uri)->assertRedirect('/parametres/abonnement');
        }
        $this->get('/parametres')->assertOk();
        $this->get('/parametres/abonnement')->assertOk()->assertSee('Votre abonnement a expiré.');
        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->artisan('subscriptions:check')->expectsOutput('0 alerte(s) créée(s).')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->where('data->type', 'subscription_expired')->count());
        $this->post('/parametres/abonnement/demande', ['message' => 'Demande QA '.$name] + $injection)->assertSessionHas('success');
        $renewal = RenewalRequest::where('agence_id', $agency->id)->firstOrFail();
        $this->assertSame($admin->id, $renewal->requested_by);
        $this->assertSame('pending', $renewal->status);
        $this->post('/parametres/abonnement/demande', $injection)->assertSessionHas('warning');
        $this->assertSame(1, RenewalRequest::where('agence_id', $agency->id)->count());
        $this->assertSame(1, $super->notifications()->where('data->type', 'renewal_requested')->count());

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $super->email, 'password' => 'password'])->assertRedirect('/super-admin/dashboard');
        $this->get('/super-admin/abonnements')->assertOk()->assertSee($name)->assertSee('En attente');
        $this->patch('/super-admin/demandes-renouvellement/'.$renewal->id, [
            'decision' => 'approved', 'type_abonnement' => $plan, 'date_debut_abonnement' => today()->toDateString(),
            'date_expiration' => today()->addYear()->toDateString(), 'montant_abonnement' => 999,
        ])->assertRedirect('/super-admin/abonnements')->assertSessionHasNoErrors();
        $this->assertSame('approved', $renewal->fresh()->status);
        $this->assertSame('actif', $agency->fresh()->statut);
        $this->assertSame(1, $admin->notifications()->where('data->type', 'renewal_approved')->count());
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'final-qa-password'])->assertRedirect('/dashboard');
        foreach (['/dashboard', '/voitures', '/clients', '/reservations', '/contrats'] as $uri) {
            $this->get($uri)->assertOk();
        }
        $this->patch('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertGreaterThan(0, $super->unreadNotifications()->count());
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_new_routes_require_authentication_and_csrf(): void
    {
        foreach ([['POST', '/parametres/abonnement/demande'], ['PATCH', '/notifications/read-all'], ['PATCH', '/notifications/unknown/read'], ['PATCH', '/super-admin/demandes-renouvellement/1']] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect('/login');
        }
        $tenant = $this->tenant('csrf-qa');
        $this->actingAs($tenant['admin']);
        $this->app->bind('env', fn () => 'production');
        $this->post('/parametres/abonnement/demande')->assertStatus(419);
        $this->patch('/notifications/read-all')->assertStatus(419);
        $this->patch('/notifications/unknown/read')->assertStatus(419);
    }

    public function test_renewals_notifications_and_subscription_are_isolated_between_agencies(): void
    {
        $a = $this->tenant('A-QA');
        $b = $this->tenant('B-QA');
        $this->actingAs($b['admin'])->post('/parametres/abonnement/demande', ['message' => 'PRIVATE-B-QA'])->assertSessionHas('success');
        $renewal = RenewalRequest::where('agence_id', $b['agency']->id)->firstOrFail();
        $notice = $b['admin']->notifications()->firstOrFail();
        $this->actingAs($a['admin'])->get('/parametres/abonnement?agency_id='.$b['agency']->id.'&subscription_id='.$b['agency']->id)
            ->assertOk()->assertDontSee('PRIVATE-B-QA')->assertDontSee($b['agency']->nom);
        $this->patch('/super-admin/demandes-renouvellement/'.$renewal->id, ['decision' => 'approved'])->assertForbidden();
        $this->patch('/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->patch('/notifications/read-all')->assertRedirect();
        $this->assertNull($notice->fresh()->read_at);
        $this->assertSame('pending', $renewal->fresh()->status);
    }

    public function test_subscription_states_preserve_expiration_boundary_and_suspension(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 17)->setTime(12, 0));
        foreach ([['actif', 60], ['actif', 30], ['actif', 15], ['actif', 7], ['actif', 3], ['actif', 1], ['actif', 0], ['actif', -1], ['essai', 5], ['suspendu', 30]] as [$status, $days]) {
            $tenant = $this->tenant($status.$days, ['statut' => $status, 'date_expiration' => today()->addDays($days)]);
            $this->assertSame(max(0, $days), $tenant['agency']->subscriptionDaysRemaining());
            $this->actingAs($tenant['admin'])->get('/parametres/abonnement')->assertOk();
            $response = $this->get('/dashboard');
            if ($status === 'suspendu' || $days < 0) {
                $response->assertRedirect('/parametres/abonnement');
            } else {
                $response->assertOk();
            }
        }
    }
}
