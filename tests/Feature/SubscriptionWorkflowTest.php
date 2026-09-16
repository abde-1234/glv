<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_state_is_centralized_and_days_never_become_negative(): void
    {
        [$active] = $this->agencyWithAdmin('Active', [
            'statut' => 'actif',
            'date_debut_abonnement' => today()->subDays(10),
            'date_expiration' => today()->addDays(20),
        ]);
        [$trial] = $this->agencyWithAdmin('Trial', ['statut' => 'essai', 'date_expiration' => today()->addDays(14)]);
        [$near] = $this->agencyWithAdmin('Near', ['statut' => 'actif', 'date_expiration' => today()->addDays(5)]);
        [$expired] = $this->agencyWithAdmin('Expired', ['statut' => 'actif', 'date_expiration' => today()->subDay()]);
        [$suspended] = $this->agencyWithAdmin('Suspended', ['statut' => 'suspendu', 'date_expiration' => today()->addMonth()]);

        $this->assertTrue($active->isSubscriptionActive());
        $this->assertTrue($active->hasValidSubscription());
        $this->assertSame(20, $active->subscriptionDaysRemaining());
        $this->assertSame(67, $active->subscriptionPeriodRemainingPercentage());
        $this->assertTrue($trial->isSubscriptionTrial());
        $this->assertTrue($trial->hasValidSubscription());
        $this->assertTrue($near->isSubscriptionExpiringSoon());
        $this->assertSame('expire', $expired->subscriptionStatus());
        $this->assertSame(0, $expired->subscriptionDaysRemaining());
        $this->assertFalse($expired->hasValidSubscription());
        $this->assertTrue($suspended->isSubscriptionSuspended());
        $this->assertSame('suspendu', $suspended->subscriptionStatus());
        $this->assertFalse($suspended->hasValidSubscription());
    }

    public function test_active_and_valid_trial_agencies_can_access_business_modules(): void
    {
        foreach ([
            ['Active', ['statut' => 'actif', 'date_expiration' => today()->addMonth()]],
            ['Trial', ['statut' => 'essai', 'date_expiration' => today()->addDays(12)]],
        ] as [$name, $attributes]) {
            [, $admin] = $this->agencyWithAdmin($name, $attributes);

            foreach (['/dashboard', '/voitures', '/clients', '/reservations', '/contrats'] as $url) {
                $this->actingAs($admin)->get($url)->assertOk();
            }
        }
    }

    public function test_expired_and_suspended_agencies_are_redirected_only_from_business_modules(): void
    {
        foreach ([
            ['Expired', ['statut' => 'actif', 'date_expiration' => today()->subDay()], 'Votre abonnement a expiré.'],
            ['Suspended', ['statut' => 'suspendu', 'date_expiration' => today()->addMonth()], 'Agence suspendue'],
        ] as [$name, $attributes, $pageMessage]) {
            [, $admin] = $this->agencyWithAdmin($name, $attributes);

            foreach (['/dashboard', '/voitures', '/clients', '/reservations', '/contrats'] as $url) {
                $this->actingAs($admin)
                    ->get($url)
                    ->assertRedirect(route('agence.settings.subscription'))
                    ->assertSessionHas('warning');
            }

            $this->actingAs($admin)
                ->get(route('agence.settings.subscription'))
                ->assertOk()
                ->assertSee($pageMessage)
                ->assertSee('Mon profil')
                ->assertSee('Se déconnecter');
            $this->actingAs($admin)->get(route('agence.profile.edit'))->assertOk();
        }
    }

    public function test_expiring_soon_and_trial_messages_are_shown_on_dashboard_and_subscription(): void
    {
        [$near, $nearAdmin] = $this->agencyWithAdmin('Near', [
            'statut' => 'actif',
            'date_expiration' => today()->addDays(5),
        ]);
        [$trial, $trialAdmin] = $this->agencyWithAdmin('Trial', [
            'statut' => 'essai',
            'date_expiration' => today()->addDays(10),
        ]);

        $this->actingAs($nearAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Votre abonnement expire dans 5 jour(s).')
            ->assertSee('Renouveler');
        $this->get(route('agence.settings.subscription'))
            ->assertOk()
            ->assertSee('Votre abonnement expire bientôt.')
            ->assertSee($near->date_expiration->format('d/m/Y'));

        $this->actingAs($trialAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Période d’essai')
            ->assertSee('10 jour(s) restant(s).');
        $this->get(route('agence.settings.subscription'))
            ->assertOk()
            ->assertSee($trial->nom)
            ->assertSee('Période d’essai');
    }

    public function test_super_admin_can_update_all_manual_subscription_fields(): void
    {
        $superAdmin = $this->superAdmin();
        [$agence] = $this->agencyWithAdmin('Agency');

        $this->actingAs($superAdmin)->patch(route('super-admin.abonnements.update', $agence), [
            'type_abonnement' => 'Pro',
            'statut' => 'actif',
            'date_debut_abonnement' => '2026-09-12',
            'date_expiration' => '2027-09-12',
            'montant_abonnement' => '1299.50',
        ])->assertRedirect(route('super-admin.abonnements.index', ['edit' => $agence->id]))
            ->assertSessionHas('success', 'Abonnement modifié avec succès.');

        $agence->refresh();
        $this->assertSame('Pro', $agence->type_abonnement);
        $this->assertSame('actif', $agence->statut);
        $this->assertSame('2026-09-12', $agence->date_debut_abonnement->format('Y-m-d'));
        $this->assertSame('2027-09-12', $agence->date_expiration->format('Y-m-d'));
        $this->assertSame('1299.50', $agence->montant_abonnement);
    }

    public function test_super_admin_subscription_validation_and_effective_status_filter(): void
    {
        $superAdmin = $this->superAdmin();
        [$active] = $this->agencyWithAdmin('Still Active', ['statut' => 'actif', 'date_expiration' => today()->addMonth()]);
        [$expired] = $this->agencyWithAdmin('Actually Expired', ['statut' => 'actif', 'date_expiration' => today()->subDay()]);

        $this->actingAs($superAdmin)
            ->from(route('super-admin.abonnements.index', ['edit' => $active->id]))
            ->patch(route('super-admin.abonnements.update', $active), [
                'type_abonnement' => 'Basic',
                'statut' => 'invalid',
                'date_debut_abonnement' => '2026-10-01',
                'date_expiration' => '2026-09-01',
                'montant_abonnement' => -1,
            ])
            ->assertSessionHasErrors(['statut', 'date_expiration', 'montant_abonnement']);

        $this->assertSame('actif', $active->refresh()->statut);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.abonnements.index', ['statut' => 'expire']))
            ->assertOk()
            ->assertSee($expired->nom)
            ->assertDontSee($active->nom);
    }

    public function test_agency_subscription_is_read_only_tenant_scoped_and_uses_configured_support(): void
    {
        PlatformSetting::query()->create([
            'platform_name' => 'GLV',
            'support_email' => 'support-reel@glv.test',
            'support_phone' => '+212 6 11 22 33 44',
        ]);
        [$agencyA, $adminA] = $this->agencyWithAdmin('Agency A', [
            'type_abonnement' => 'Basic',
            'montant_abonnement' => 499,
        ]);
        [$agencyB] = $this->agencyWithAdmin('Agency B', [
            'type_abonnement' => 'Pro',
            'montant_abonnement' => 999,
        ]);

        $this->actingAs($adminA)
            ->get(route('agence.settings.subscription'))
            ->assertOk()
            ->assertSee($agencyA->nom)
            ->assertSee('499,00 MAD')
            ->assertSee('support-reel@glv.test')
            ->assertSee('+212 6 11 22 33 44')
            ->assertDontSee($agencyB->nom)
            ->assertDontSee('999,00 MAD');

        $this->put('/parametres/abonnement', ['agence_id' => $agencyB->id, 'statut' => 'suspendu'])
            ->assertStatus(405);
        $this->patch(route('super-admin.abonnements.update', $agencyA), ['statut' => 'suspendu'])
            ->assertForbidden();
        $this->assertSame('actif', $agencyA->refresh()->statut);
    }

    public function test_expired_agency_can_log_out_without_a_redirect_loop(): void
    {
        [, $admin] = $this->agencyWithAdmin('Expired', [
            'statut' => 'actif',
            'date_expiration' => today()->subDay(),
        ]);

        $this->actingAs($admin)->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('agence.settings.subscription'));
        $this->get(route('agence.settings.subscription'))->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function agencyWithAdmin(string $name, array $attributes = []): array
    {
        $agence = Agence::query()->create(array_merge([
            'nom' => $name,
            'ville' => 'Fès',
            'statut' => 'actif',
            'type_abonnement' => 'Basic',
            'date_debut_abonnement' => today(),
            'date_expiration' => today()->addYear(),
            'montant_abonnement' => 499,
        ], $attributes));
        $admin = User::factory()->create([
            'agence_id' => $agence->id,
            'role' => User::ROLE_ADMIN_AGENCE,
            'statut' => 'actif',
        ]);

        return [$agence, $admin];
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'agence_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
            'statut' => 'actif',
        ]);
    }
}
