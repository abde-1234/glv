<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\RenewalRequest;
use App\Models\User;
use App\Notifications\GlvNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubscriptionRenewalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_can_create_only_one_pending_request_and_cannot_inject_ownership_or_status(): void
    {
        [$agency, $admin] = $this->agency('Atlas Rent');
        [$other] = $this->agency('Rif Car');

        $this->actingAs($admin)->post(route('agence.renewals.store'), [
            'message' => 'Merci de renouveler.',
            'agence_id' => $other->id,
            'subscription_id' => 999,
            'status' => RenewalRequest::APPROVED,
            'montant' => 0,
        ])->assertRedirect()->assertSessionHas('success');

        $renewal = RenewalRequest::firstOrFail();
        $this->assertSame($agency->id, $renewal->agence_id);
        $this->assertSame($admin->id, $renewal->requested_by);
        $this->assertSame(RenewalRequest::PENDING, $renewal->status);

        $this->post(route('agence.renewals.store'), ['message' => 'Encore'])
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(1, RenewalRequest::count());
        $this->assertSame('renewal_requested', $admin->notifications()->firstOrFail()->data['type']);
    }

    public function test_super_admin_sees_request_is_notified_and_can_approve_expired_agency(): void
    {
        $super = $this->superAdmin();
        [$agency, $admin] = $this->agency('Expired Car', ['statut' => 'expire', 'date_expiration' => today()->subDay()]);

        $this->actingAs($admin)->post(route('agence.renewals.store'), ['message' => 'Renouvellement'])->assertRedirect();
        $renewal = RenewalRequest::firstOrFail();
        $this->assertSame('renewal_requested', $super->notifications()->firstOrFail()->data['type']);

        $this->actingAs($super)->get(route('super-admin.abonnements.index'))
            ->assertOk()->assertSee('Expired Car')->assertSee('Demandes de renouvellement');

        $this->patch(route('super-admin.renewals.update', $renewal), [
            'decision' => RenewalRequest::APPROVED,
            'type_abonnement' => 'Premium',
            'date_debut_abonnement' => today()->format('Y-m-d'),
            'date_expiration' => today()->addYear()->format('Y-m-d'),
            'montant_abonnement' => 1499,
        ])->assertRedirect(route('super-admin.abonnements.index'))->assertSessionHas('success');

        $agency->refresh();
        $this->assertSame('actif', $agency->statut);
        $this->assertSame('Premium', $agency->type_abonnement);
        $this->assertTrue($agency->hasValidSubscription());
        $this->assertSame(RenewalRequest::APPROVED, $renewal->refresh()->status);
        $this->assertSame(1, $admin->notifications()->where('data->type', 'renewal_approved')->count());
    }

    public function test_approval_never_removes_administrative_suspension(): void
    {
        $super = $this->superAdmin();
        [$agency, $admin] = $this->agency('Suspended', ['statut' => 'suspendu']);
        $this->actingAs($admin)->post(route('agence.renewals.store'))->assertRedirect();

        $this->actingAs($super)->patch(route('super-admin.renewals.update', RenewalRequest::firstOrFail()), [
            'decision' => RenewalRequest::APPROVED,
            'type_abonnement' => 'Pro',
            'date_debut_abonnement' => today()->format('Y-m-d'),
            'date_expiration' => today()->addYear()->format('Y-m-d'),
            'montant_abonnement' => 999,
        ])->assertRedirect();

        $this->assertSame('suspendu', $agency->refresh()->statut);
        $this->assertFalse($agency->hasValidSubscription());
    }

    public function test_rejection_records_decision_and_notifies_agency(): void
    {
        $super = $this->superAdmin();
        [, $admin] = $this->agency('Ocean Cars');
        $this->actingAs($admin)->post(route('agence.renewals.store'))->assertRedirect();
        $renewal = RenewalRequest::firstOrFail();

        $this->actingAs($super)->patch(route('super-admin.renewals.update', $renewal), [
            'decision' => RenewalRequest::REJECTED,
            'decision_message' => 'Informations manquantes.',
        ])->assertRedirect();

        $this->assertSame(RenewalRequest::REJECTED, $renewal->refresh()->status);
        $this->assertNull($renewal->pending_key);
        $this->assertSame(1, $admin->notifications()->where('data->type', 'renewal_rejected')->count());
    }

    public function test_notifications_are_private_and_can_be_marked_read(): void
    {
        [, $adminA] = $this->agency('A');
        [, $adminB] = $this->agency('B');
        $adminA->notify(new GlvNotification(['type' => 'test', 'title' => 'Privée', 'message' => 'A', 'url' => route('agence.settings.subscription')]));
        $notification = $adminA->notifications()->firstOrFail();

        $this->actingAs($adminB)->patch(route('notifications.read', $notification))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($adminA)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_scheduler_creates_all_thresholds_once_without_duplicates(): void
    {
        $expected = [30 => 'expiration_30_days', 15 => 'expiration_15_days', 7 => 'expiration_7_days', 3 => 'expiration_3_days', 1 => 'expiration_1_day', -1 => 'subscription_expired'];
        $admins = [];
        foreach ($expected as $days => $type) {
            [, $admins[$type]] = $this->agency('Agency '.$days, ['date_expiration' => today()->addDays($days)]);
        }

        Artisan::call('subscriptions:check');
        Artisan::call('subscriptions:check');

        foreach ($admins as $type => $admin) {
            $this->assertSame(1, $admin->notifications()->where('data->type', $type)->count(), $type);
        }
        $this->assertDatabaseCount('subscription_notification_events', 6);
    }

    private function agency(string $name, array $attributes = []): array
    {
        $agency = Agence::query()->create(array_merge([
            'nom' => $name,
            'statut' => 'actif',
            'type_abonnement' => 'Pro',
            'date_debut_abonnement' => today()->subMonth(),
            'date_expiration' => today()->addMonth(),
            'montant_abonnement' => 999,
        ], $attributes));
        $admin = User::factory()->create(['agence_id' => $agency->id, 'role' => User::ROLE_ADMIN_AGENCE, 'statut' => 'actif']);

        return [$agency, $admin];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['agence_id' => null, 'role' => User::ROLE_SUPER_ADMIN, 'statut' => 'actif']);
    }
}
