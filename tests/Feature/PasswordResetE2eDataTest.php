<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordResetE2eDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_all_expected_statuses_with_valid_relations(): void
    {
        $accounts = $this->createRequiredAccounts();

        $this->artisan('glv:e2e-password-reset-data')
            ->expectsOutput('Agences trouvées : 3')
            ->expectsOutput('Admins trouvés : 3')
            ->expectsOutput('Demandes créées : 5')
            ->expectsOutput('Notifications créées : 3')
            ->expectsOutput('Orphelines : 0')
            ->expectsOutput('Doublons : 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('password_reset_requests', 5);
        $this->assertDatabaseCount('password_reset_request_events', 10);
        foreach (['pending', 'approved', 'rejected', 'completed', 'expired'] as $status) {
            $this->assertDatabaseHas('password_reset_requests', ['status' => $status]);
        }

        $this->assertDatabaseHas('password_reset_requests', [
            'agence_id' => $accounts['Atlas Mobility']->id,
            'user_id' => $accounts['admin@atlas-mobility.test']->id,
            'status' => 'pending',
            'processed_by' => null,
        ]);
        $this->assertDatabaseHas('password_reset_requests', [
            'agence_id' => $accounts['Palm Cars']->id,
            'user_id' => $accounts['admin@palm-cars.test']->id,
            'status' => 'rejected',
            'processed_by' => $accounts['admin@glv.test']->id,
        ]);
    }

    public function test_super_admin_can_see_seeded_requests_and_open_pending_details(): void
    {
        $accounts = $this->seedDataset();
        $pending = PasswordResetRequest::query()->where('status', 'pending')->firstOrFail();

        $this->actingAs($accounts['admin@glv.test'])
            ->get(route('super-admin.password-resets.index'))
            ->assertOk()
            ->assertSee('Atlas Mobility')
            ->assertSee('Rif Drive')
            ->assertSee('Palm Cars')
            ->assertSee('admin@atlas-mobility.test');

        $this->get(route('super-admin.password-resets.show', $pending))
            ->assertOk()
            ->assertSee('Atlas Mobility')
            ->assertSee('admin@atlas-mobility.test')
            ->assertSee('Réinitialiser le mot de passe');
    }

    public function test_search_by_agency_and_email_is_scoped_to_matches(): void
    {
        $accounts = $this->seedDataset();
        $this->actingAs($accounts['admin@glv.test']);

        $this->get(route('super-admin.password-resets.index', ['q' => 'Atlas']))
            ->assertOk()->assertSee('Atlas Mobility')->assertDontSee('Palm Cars');
        $this->get(route('super-admin.password-resets.index', ['q' => 'Rif']))
            ->assertOk()->assertSee('Rif Drive')->assertDontSee('Atlas Mobility');
        $this->get(route('super-admin.password-resets.index', ['q' => 'Palm']))
            ->assertOk()->assertSee('Palm Cars')->assertDontSee('Rif Drive');
        $this->get(route('super-admin.password-resets.index', ['q' => 'admin@palm-cars.test']))
            ->assertOk()
            ->assertSee('data-label="Email">admin@palm-cars.test</td>', false)
            ->assertDontSee('data-label="Email">admin@atlas-mobility.test</td>', false);
    }

    public function test_status_filters_show_pending_rejected_approved_completed_and_expired(): void
    {
        $accounts = $this->seedDataset();
        $this->actingAs($accounts['admin@glv.test']);

        foreach (['pending', 'approved', 'rejected', 'completed', 'expired'] as $status) {
            $response = $this->get(route('super-admin.password-resets.index', ['status' => $status]))->assertOk();
            $this->assertSame(1, substr_count($response->getContent(), 'class="reset-status is-'.$status.'"'));
        }
    }

    public function test_agency_admin_is_forbidden_from_list_detail_and_processing(): void
    {
        $accounts = $this->seedDataset();
        $request = PasswordResetRequest::query()->where('agence_id', $accounts['Rif Drive']->id)->firstOrFail();

        $this->actingAs($accounts['admin@atlas-mobility.test'])
            ->get(route('super-admin.password-resets.index'))->assertForbidden();
        $this->get(route('super-admin.password-resets.show', $request))->assertForbidden();
        $this->patch(route('super-admin.password-resets.approve', $request), [
            'agency_id' => $accounts['Atlas Mobility']->id,
            'user_id' => $accounts['admin@atlas-mobility.test']->id,
            'requested_by' => $accounts['admin@atlas-mobility.test']->id,
            'status' => 'completed',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertForbidden();
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($accounts['Rif Drive']->id, $request->agence_id);
    }

    public function test_command_is_idempotent_for_requests_and_notifications(): void
    {
        $this->createRequiredAccounts();

        $this->artisan('glv:e2e-password-reset-data')->assertSuccessful();
        $this->artisan('glv:e2e-password-reset-data')
            ->expectsOutput('Demandes créées : 0')
            ->expectsOutput('Demandes existantes ignorées : 5')
            ->expectsOutput('Notifications créées : 0')
            ->expectsOutput('Notifications existantes ignorées : 3')
            ->assertSuccessful();

        $this->assertDatabaseCount('password_reset_requests', 5);
        $this->assertDatabaseCount('password_reset_request_events', 10);
        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(1, PasswordResetRequest::query()->where('status', 'pending')->where('user_id', User::where('email', 'admin@atlas-mobility.test')->value('id'))->count());
    }

    public function test_command_reuses_an_existing_active_request_instead_of_violating_pending_uniqueness(): void
    {
        $accounts = $this->createRequiredAccounts();
        PasswordResetRequest::create([
            'agence_id' => $accounts['Atlas Mobility']->id,
            'user_id' => $accounts['admin@atlas-mobility.test']->id,
            'status' => PasswordResetRequest::STATUS_APPROVED,
            'pending_key' => $accounts['admin@atlas-mobility.test']->id,
            'requested_at' => now()->subHour(),
            'processed_at' => now()->subMinutes(50),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('glv:e2e-password-reset-data')->assertSuccessful();

        $this->assertSame(1, PasswordResetRequest::query()->where('user_id', $accounts['admin@atlas-mobility.test']->id)->active()->count());
        $this->assertDatabaseCount('password_reset_requests', 5);
        $this->assertDatabaseCount('password_reset_request_events', 11);
    }

    public function test_command_refuses_to_run_in_production(): void
    {
        $this->createRequiredAccounts();
        $this->app->instance('env', 'production');

        try {
            $this->artisan('glv:e2e-password-reset-data')
                ->expectsOutput('Cette commande est réservée aux environnements local/test.')
                ->assertFailed();
        } finally {
            $this->app->instance('env', 'testing');
        }

        $this->assertDatabaseCount('password_reset_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_dataset_has_no_orphans_duplicates_or_cross_agency_associations(): void
    {
        $accounts = $this->seedDataset();

        $orphans = DB::table('password_reset_requests as requests')
            ->leftJoin('agences', 'agences.id', '=', 'requests.agence_id')
            ->leftJoin('users', 'users.id', '=', 'requests.user_id')
            ->whereNull('agences.id')->orWhereNull('users.id')->count();
        $duplicates = PasswordResetRequest::query()->select('user_id')->where('status', 'pending')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->count();

        $this->assertSame(0, $orphans);
        $this->assertSame(0, $duplicates);
        foreach (PasswordResetRequest::query()->with('user')->get() as $request) {
            $this->assertSame($request->agence_id, $request->user->agence_id);
        }
        $this->assertSame(3, DB::table('notifications')->count());
        $this->assertSame('actif', $accounts['admin@atlas-mobility.test']->fresh()->statut);
    }

    /** @return array<string, Agence|User> */
    private function seedDataset(): array
    {
        $accounts = $this->createRequiredAccounts();
        $this->artisan('glv:e2e-password-reset-data')->assertSuccessful();

        return $accounts;
    }

    /** @return array<string, Agence|User> */
    private function createRequiredAccounts(): array
    {
        $accounts = [];
        $accounts['admin@glv.test'] = User::factory()->create([
            'agence_id' => null,
            'email' => 'admin@glv.test',
            'role' => User::ROLE_SUPER_ADMIN,
            'statut' => 'actif',
        ]);

        foreach ([
            'Atlas Mobility' => 'admin@atlas-mobility.test',
            'Rif Drive' => 'admin@rif-drive.test',
            'Palm Cars' => 'admin@palm-cars.test',
        ] as $agencyName => $email) {
            $agency = Agence::create(['nom' => $agencyName, 'email' => 'contact@'.str($agencyName)->slug().'.test', 'statut' => 'actif', 'date_expiration' => today()->addYear()]);
            $admin = User::factory()->create(['agence_id' => $agency->id, 'email' => $email, 'role' => User::ROLE_ADMIN_AGENCE, 'statut' => 'actif']);
            $accounts[$agencyName] = $agency;
            $accounts[$email] = $admin;
        }

        return $accounts;
    }
}
