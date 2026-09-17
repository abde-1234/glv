<?php

namespace Tests\Feature;

use App\Models\RenewalRequest;
use App\Models\User;
use App\Notifications\GlvNotification;
use App\Services\SubscriptionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

// Tests d'acceptation de l'audit : un échec signale un défaut restant,
// il ne faut pas remplacer l'attendu par le comportement défectueux.
class FinalQaRegressionTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_disabled_account_cannot_mark_notifications_read(): void
    {
        $tenant = $this->tenant('disabled-notice');
        $user = $tenant['admin'];
        $user->notify(new GlvNotification(['title' => 'Privée', 'message' => 'QA', 'url' => route('agence.settings.subscription')]));
        $notice = $user->notifications()->firstOrFail();
        $user->update(['statut' => 'inactif']);
        $this->actingAs($user->fresh())->patch('/notifications/'.$notice->id.'/read')->assertForbidden();
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_approval_rejects_a_period_that_has_already_expired(): void
    {
        $tenant = $this->tenant('expired-renewal', ['date_expiration' => today()->subMonth()]);
        $this->actingAs($tenant['admin'])->post('/parametres/abonnement/demande')->assertSessionHas('success');
        $renewal = RenewalRequest::firstOrFail();
        $this->actingAs($this->superAdmin())->patch('/super-admin/demandes-renouvellement/'.$renewal->id, [
            'decision' => 'approved', 'type_abonnement' => 'Pro', 'montant_abonnement' => 999,
            'date_debut_abonnement' => today()->subDays(15)->toDateString(),
            'date_expiration' => today()->subDay()->toDateString(),
        ])->assertSessionHasErrors('date_expiration');
        $this->assertSame('pending', $renewal->fresh()->status);
    }

    public function test_expiration_alert_can_be_retried_after_notification_delivery_fails(): void
    {
        $tenant = $this->tenant('retry-alert', ['date_expiration' => today()->addDays(7)]);
        $service = app(SubscriptionNotificationService::class);
        $originalSender = Notification::getFacadeRoot();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('QA delivery failure'));
        try {
            $service->check($tenant['agency']);
            $this->fail('Le défaut simulé doit interrompre le premier envoi.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('QA delivery failure', $exception->getMessage());
        } finally {
            Notification::swap($originalSender);
        }

        $this->assertTrue($service->check($tenant['agency']), 'Une alerte non livrée doit pouvoir être réessayée.');
        $this->assertSame(1, $tenant['admin']->notifications()->where('data->type', 'expiration_7_days')->count());
    }

    public function test_super_admin_can_read_the_message_before_processing_a_request(): void
    {
        $tenant = $this->tenant('request-message');
        $this->actingAs($tenant['admin'])->post('/parametres/abonnement/demande', ['message' => 'QA-MESSAGE-AGENCE-POUR-TRAITEMENT'])->assertSessionHas('success');
        $renewal = RenewalRequest::firstOrFail();
        $this->actingAs($this->superAdmin())->get('/super-admin/abonnements?renewal='.$renewal->id)
            ->assertOk()->assertSee('QA-MESSAGE-AGENCE-POUR-TRAITEMENT');
    }

    public function test_agency_can_read_the_reason_for_a_rejected_renewal(): void
    {
        $tenant = $this->tenant('rejection-reason');
        $this->actingAs($tenant['admin'])->post('/parametres/abonnement/demande')->assertSessionHas('success');
        $renewal = RenewalRequest::firstOrFail();
        $this->actingAs($this->superAdmin())->patch('/super-admin/demandes-renouvellement/'.$renewal->id, [
            'decision' => 'rejected', 'decision_message' => 'QA-MOTIF-REFUS-A-COMMUNIQUER',
        ])->assertSessionHas('success');
        $this->actingAs($tenant['admin'])->get('/parametres/abonnement')
            ->assertOk()->assertSee('QA-MOTIF-REFUS-A-COMMUNIQUER');
    }

    public function test_explicit_expired_status_produces_an_expiration_notification(): void
    {
        $tenant = $this->tenant('expired-status', ['statut' => 'expire', 'date_expiration' => today()->addDays(60)]);
        $this->assertFalse($tenant['agency']->hasValidSubscription());
        $this->artisan('subscriptions:check')->assertSuccessful();
        $this->assertSame(1, $tenant['admin']->notifications()->where('data->type', 'subscription_expired')->count());
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'agence_id' => null, 'statut' => 'actif']);
    }
}
