<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Models\Agence;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetRequestEvent;
use App\Models\User;
use App\Notifications\AgencyPasswordResetLink;
use App\Notifications\AgencyPasswordResetRejected;
use App\Notifications\GlvNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_forgot_password_page_is_public(): void
    {
        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Mot de passe oublié')->assertSee('Retour à la connexion');
    }

    public function test_02_valid_form_returns_the_generic_confirmation(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email])->assertSessionHas('status', ForgotPasswordController::GENERIC_MESSAGE);
    }

    public function test_03_invalid_form_is_rejected(): void
    {
        $this->post('/mot-de-passe-oublie', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    public function test_04_request_is_created_for_an_agency_admin(): void
    {
        [$agency, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => strtoupper($admin->email)]);
        $this->assertDatabaseHas('password_reset_requests', ['agence_id' => $agency->id, 'user_id' => $admin->id, 'status' => 'pending']);
    }

    public function test_05_super_admin_is_notified(): void
    {
        Notification::fake();
        $super = $this->superAdmin();
        [$agency, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        Notification::assertSentTo($super, GlvNotification::class, fn ($notice) => $notice->toArray($super)['type'] === 'password_reset_request' && str_contains($notice->toArray($super)['message'], $agency->nom));
    }

    public function test_06_duplicate_pending_request_is_blocked(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        $this->assertDatabaseCount('password_reset_requests', 1);
    }

    public function test_07_agency_a_cannot_view_agency_b_request(): void
    {
        [, $agencyAAdmin] = $this->agencyAdmin('A');
        [, $agencyBAdmin] = $this->agencyAdmin('B');
        $resetRequest = $this->pendingRequest($agencyBAdmin);
        $this->actingAs($agencyAAdmin)->get(route('super-admin.password-resets.show', $resetRequest))->assertForbidden();
    }

    public function test_08_agency_a_cannot_process_agency_b_request(): void
    {
        [, $agencyAAdmin] = $this->agencyAdmin('A');
        [, $agencyBAdmin] = $this->agencyAdmin('B');
        $resetRequest = $this->pendingRequest($agencyBAdmin);
        $this->actingAs($agencyAAdmin)->patch(route('super-admin.password-resets.approve', $resetRequest))->assertForbidden();
        $this->assertSame('pending', $resetRequest->fresh()->status);
    }

    public function test_09_agency_id_cannot_be_injected(): void
    {
        [$agency, $admin] = $this->agencyAdmin('A');
        [$otherAgency] = $this->agencyAdmin('B');
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email, 'agence_id' => $otherAgency->id]);
        $this->assertSame($agency->id, PasswordResetRequest::firstOrFail()->agence_id);
    }

    public function test_10_user_id_cannot_be_injected(): void
    {
        [, $admin] = $this->agencyAdmin('A');
        [, $otherAdmin] = $this->agencyAdmin('B');
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email, 'user_id' => $otherAdmin->id]);
        $this->assertSame($admin->id, PasswordResetRequest::firstOrFail()->user_id);
    }

    public function test_11_role_cannot_be_injected(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email, 'role' => User::ROLE_SUPER_ADMIN]);
        $this->assertSame(User::ROLE_ADMIN_AGENCE, $admin->fresh()->role);
    }

    public function test_12_status_cannot_be_injected(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email, 'status' => 'completed']);
        $this->assertSame('pending', PasswordResetRequest::firstOrFail()->status);
    }

    public function test_13_super_admin_can_approve_and_send_a_one_use_link(): void
    {
        Notification::fake();
        $super = $this->superAdmin();
        [, $admin] = $this->agencyAdmin();
        $resetRequest = $this->pendingRequest($admin);
        $this->actingAs($super)->patch(route('super-admin.password-resets.approve', $resetRequest))->assertSessionHas('success');
        $this->assertSame('approved', $resetRequest->fresh()->status);
        Notification::assertSentTo($admin, AgencyPasswordResetLink::class);
    }

    public function test_14_super_admin_can_reject_with_a_reason(): void
    {
        Notification::fake();
        $resetRequest = $this->pendingRequest($this->agencyAdmin()[1]);
        $this->actingAs($this->superAdmin())->patch(route('super-admin.password-resets.reject', $resetRequest), ['rejection_reason' => 'Identité à confirmer.'])->assertSessionHas('success');
        $this->assertDatabaseHas('password_reset_requests', ['id' => $resetRequest->id, 'status' => 'rejected', 'rejection_reason' => 'Identité à confirmer.', 'pending_key' => null]);
        Notification::assertSentTo($resetRequest->user, AgencyPasswordResetRejected::class);
    }

    public function test_15_agency_admin_is_notified_after_processing(): void
    {
        Notification::fake();
        [, $admin] = $this->agencyAdmin();
        $resetRequest = $this->pendingRequest($admin);
        $this->actingAs($this->superAdmin())->patch(route('super-admin.password-resets.approve', $resetRequest));
        Notification::assertSentTo($admin, GlvNotification::class, fn ($notice) => $notice->toArray($admin)['type'] === 'password_reset_approved');
    }

    public function test_16_old_password_is_invalid_after_reset(): void
    {
        [$admin, $token, $resetRequest] = $this->approvedRequest();
        $this->post(route('password.update'), $this->resetPayload($admin, $token, 'NewSecure123'))->assertRedirect(route('login'));
        $this->assertFalse(Hash::check('OldSecure123', $admin->fresh()->password));
        $this->assertSame('completed', $resetRequest->fresh()->status);
    }

    public function test_17_new_password_works_and_token_cannot_be_replayed(): void
    {
        [$admin, $token] = $this->approvedRequest();
        $payload = $this->resetPayload($admin, $token, 'NewSecure123');
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $admin->email, 'password' => 'NewSecure123'])->assertRedirect('/dashboard');
    }

    public function test_18_suspended_agency_stays_suspended(): void
    {
        [$agency, $admin] = $this->agencyAdmin('Suspended', ['statut' => 'suspendu']);
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        $this->assertSame('suspendu', $agency->fresh()->statut);
        $this->assertDatabaseHas('password_reset_requests', ['user_id' => $admin->id]);
    }

    public function test_19_expired_agency_can_recover_access_without_reactivation(): void
    {
        [$agency, $admin] = $this->agencyAdmin('Expired', ['statut' => 'expire', 'date_expiration' => now()->subDay()]);
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        $this->assertSame('expire', $agency->fresh()->statut);
        $this->assertDatabaseHas('password_reset_requests', ['user_id' => $admin->id]);
    }

    public function test_20_csrf_protects_public_and_admin_mutations(): void
    {
        [, $admin] = $this->agencyAdmin();
        $this->app->instance('env', 'local');
        try {
            $this->post('/mot-de-passe-oublie', ['email' => $admin->email])->assertStatus(419);
            $this->actingAs($this->superAdmin())->patch('/super-admin/demandes-reinitialisation/1/approuver')->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_21_request_endpoint_is_rate_limited(): void
    {
        [, $admin] = $this->agencyAdmin();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/mot-de-passe-oublie', ['email' => $admin->email])->assertRedirect();
        }
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email])->assertStatus(429);
    }

    public function test_22_unknown_inactive_and_valid_accounts_have_the_same_public_response(): void
    {
        [, $valid] = $this->agencyAdmin('Valid');
        [, $inactive] = $this->agencyAdmin('Inactive');
        $inactive->update(['statut' => 'inactif']);
        foreach ([$valid->email, $inactive->email, 'unknown@example.test'] as $email) {
            $this->post('/mot-de-passe-oublie', ['email' => $email])->assertSessionHas('status', ForgotPasswordController::GENERIC_MESSAGE);
        }
        $this->assertDatabaseCount('password_reset_requests', 1);
    }

    public function test_23_requester_receives_one_submission_notification_and_one_audit_event(): void
    {
        Notification::fake();
        [, $admin] = $this->agencyAdmin();

        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);
        $this->post('/mot-de-passe-oublie', ['email' => $admin->email]);

        Notification::assertSentToTimes($admin, GlvNotification::class, 1);
        $this->assertDatabaseCount('password_reset_request_events', 1);
        $this->assertDatabaseHas('password_reset_request_events', ['event_type' => PasswordResetRequestEvent::TYPE_CREATED, 'actor_id' => $admin->id]);
    }

    public function test_24_approval_rejection_expiration_and_completion_are_audited_without_secrets(): void
    {
        Notification::fake();
        $super = $this->superAdmin();
        [, $approvedAdmin] = $this->agencyAdmin('Approved');
        $approved = $this->pendingRequest($approvedAdmin);
        $this->actingAs($super)->patch(route('super-admin.password-resets.approve', $approved));

        $token = null;
        Notification::assertSentTo($approvedAdmin, AgencyPasswordResetLink::class, function (AgencyPasswordResetLink $notice) use (&$token): bool {
            $token = $notice->token;

            return true;
        });
        $approvalTime = $approved->fresh()->processed_at;
        $this->post('/logout');
        $this->post(route('password.update'), $this->resetPayload($approvedAdmin, $token, 'NewSecure123'));

        [, $rejectedAdmin] = $this->agencyAdmin('Rejected');
        $rejected = $this->pendingRequest($rejectedAdmin);
        $this->actingAs($super)->patch(route('super-admin.password-resets.reject', $rejected), ['rejection_reason' => 'Identité à confirmer.']);

        [, $expiredAdmin] = $this->agencyAdmin('ExpiredAudit');
        $expired = $this->pendingRequest($expiredAdmin);
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->get(route('super-admin.password-resets.show', $expired));

        $this->assertSame($approvalTime->toDateTimeString(), $approved->fresh()->processed_at->toDateTimeString());
        $this->assertSame([
            PasswordResetRequestEvent::TYPE_APPROVED,
            PasswordResetRequestEvent::TYPE_RESET_USED,
            PasswordResetRequestEvent::TYPE_PASSWORD_CHANGED,
        ], $approved->events()->pluck('event_type')->all());
        $this->assertDatabaseHas('password_reset_request_events', ['password_reset_request_id' => $rejected->id, 'event_type' => PasswordResetRequestEvent::TYPE_REJECTED]);
        $this->assertDatabaseHas('password_reset_request_events', ['password_reset_request_id' => $expired->id, 'event_type' => PasswordResetRequestEvent::TYPE_EXPIRED]);
        $serializedAudit = DB::table('password_reset_request_events')->pluck('metadata')->implode(' ');
        $this->assertStringNotContainsString((string) $token, $serializedAudit);
        $this->assertStringNotContainsString('NewSecure123', $serializedAudit);
    }

    public function test_25_expired_request_or_inactive_user_cannot_use_an_otherwise_valid_token(): void
    {
        [$expiredAdmin, $expiredToken, $expiredRequest] = $this->approvedRequest('ExpiredToken');
        $expiredRequest->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->post(route('password.update'), $this->resetPayload($expiredAdmin, $expiredToken, 'NewSecure123'))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldSecure123', $expiredAdmin->fresh()->password));

        [$inactiveAdmin, $inactiveToken] = $this->approvedRequest('InactiveToken');
        $inactiveAdmin->update(['statut' => 'inactif']);
        $this->post(route('password.update'), $this->resetPayload($inactiveAdmin, $inactiveToken, 'NewSecure123'))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldSecure123', $inactiveAdmin->fresh()->password));
    }

    public function test_26_processing_is_idempotent_and_refusal_input_is_bounded_and_escaped(): void
    {
        Notification::fake();
        $super = $this->superAdmin();
        $resetRequest = $this->pendingRequest($this->agencyAdmin()[1]);

        $this->actingAs($super)->patch(route('super-admin.password-resets.approve', $resetRequest))->assertSessionHas('success');
        $this->patch(route('super-admin.password-resets.approve', $resetRequest))->assertSessionHasErrors('request');
        $this->patch(route('super-admin.password-resets.reject', $resetRequest), ['rejection_reason' => 'Après'])->assertSessionHasErrors('request');
        $this->assertSame(1, $resetRequest->events()->where('event_type', PasswordResetRequestEvent::TYPE_APPROVED)->count());

        $second = $this->pendingRequest($this->agencyAdmin('Xss')[1]);
        $this->patch(route('super-admin.password-resets.reject', $second), [])->assertSessionHasErrors('rejection_reason');
        $this->patch(route('super-admin.password-resets.reject', $second), ['rejection_reason' => str_repeat('a', 2001)])->assertSessionHasErrors('rejection_reason');
        $this->patch(route('super-admin.password-resets.reject', $second), ['rejection_reason' => '<script>alert(1)</script>'])->assertSessionHas('success');
        $this->get(route('super-admin.password-resets.show', $second))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_27_mismatched_agency_user_pair_cannot_be_approved(): void
    {
        [$agencyA, $adminA] = $this->agencyAdmin('MismatchA');
        [$agencyB] = $this->agencyAdmin('MismatchB');
        $resetRequest = $this->pendingRequest($adminA);
        $resetRequest->forceFill(['agence_id' => $agencyB->id])->save();

        $this->actingAs($this->superAdmin())->patch(route('super-admin.password-resets.approve', $resetRequest))->assertSessionHasErrors('request');
        $this->assertSame('pending', $resetRequest->fresh()->status);
        $this->assertNotSame($agencyA->id, $resetRequest->fresh()->agence_id);
    }

    public function test_28_notification_actions_are_owner_scoped_idempotent_and_block_external_redirects(): void
    {
        [, $owner] = $this->agencyAdmin('Owner');
        [, $other] = $this->agencyAdmin('Other');
        $owner->notify(new GlvNotification(['title' => 'Test', 'message' => 'Safe', 'url' => 'https://evil.example/phish']));
        $notice = $owner->notifications()->firstOrFail();

        $this->actingAs($other)->patch(route('notifications.read', $notice))->assertNotFound();
        $this->actingAs($owner)->patch(route('notifications.read', $notice))->assertRedirect();
        $this->assertNotSame('https://evil.example/phish', $this->patch(route('notifications.read', $notice))->headers->get('Location'));
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->patch(route('notifications.read', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
    }

    public function test_29_sensitive_actions_reject_get_and_deleted_users_leave_no_notification_orphans(): void
    {
        [, $admin] = $this->agencyAdmin('Delete');
        $admin->notify(new GlvNotification(['title' => 'Test', 'message' => 'Delete', 'url' => route('login')]));
        $notificationId = $admin->notifications()->firstOrFail()->id;
        $resetRequest = $this->pendingRequest($admin);

        $this->actingAs($this->superAdmin())->get(route('super-admin.password-resets.approve', $resetRequest))->assertMethodNotAllowed();
        $admin->delete();

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
        $this->assertDatabaseMissing('password_reset_requests', ['id' => $resetRequest->id]);
    }

    public function test_30_reset_email_has_a_clear_subject_secure_internal_link_and_expiration(): void
    {
        app()->setLocale('fr');
        [, $admin] = $this->agencyAdmin('Mail');
        $notice = new AgencyPasswordResetLink('safe-test-token');
        $mail = $notice->toMail($admin);

        $this->assertSame('Réinitialisation de votre accès GLV', $mail->subject);
        $this->assertStringStartsWith(config('app.url').'/reinitialiser-mot-de-passe/safe-test-token', $mail->actionUrl);
        $this->assertStringContainsString('email='.urlencode($admin->email), $mail->actionUrl);
        $mailText = implode(' ', [...$mail->introLines, ...$mail->outroLines]);
        $this->assertStringContainsString((string) config('auth.passwords.users.expire'), $mailText);
        $this->assertStringNotContainsString('OldSecure123', $mailText);
    }

    public function test_31_rejection_email_removes_html_from_the_user_supplied_reason(): void
    {
        app()->setLocale('fr');
        [, $admin] = $this->agencyAdmin('RejectedMail');
        $mail = (new AgencyPasswordResetRejected('<script>alert(1)</script>Identité à confirmer.'))->toMail($admin);
        $mailText = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

        $this->assertStringNotContainsString('<script>', $mailText);
        $this->assertStringContainsString('alert(1)Identité à confirmer.', $mailText);
    }

    private function agencyAdmin(string $suffix = 'Main', array $agencyAttributes = []): array
    {
        $agency = Agence::create(array_merge(['nom' => 'Agence '.$suffix, 'email' => strtolower($suffix).'@agency.test', 'statut' => 'actif', 'date_expiration' => now()->addYear()], $agencyAttributes));
        $admin = User::factory()->create(['agence_id' => $agency->id, 'role' => User::ROLE_ADMIN_AGENCE, 'statut' => 'actif', 'email' => strtolower($suffix).'@admin.test', 'password' => 'OldSecure123']);

        return [$agency, $admin];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['agence_id' => null, 'role' => User::ROLE_SUPER_ADMIN, 'statut' => 'actif']);
    }

    private function pendingRequest(User $admin): PasswordResetRequest
    {
        return PasswordResetRequest::create(['agence_id' => $admin->agence_id, 'user_id' => $admin->id, 'status' => 'pending', 'pending_key' => $admin->id, 'requested_at' => now(), 'expires_at' => now()->addDay()]);
    }

    private function approvedRequest(string $suffix = 'Main'): array
    {
        Notification::fake();
        [, $admin] = $this->agencyAdmin($suffix);
        $resetRequest = $this->pendingRequest($admin);
        $this->actingAs($this->superAdmin())->patch(route('super-admin.password-resets.approve', $resetRequest));
        $token = null;
        Notification::assertSentTo($admin, AgencyPasswordResetLink::class, function (AgencyPasswordResetLink $notice) use (&$token): bool {
            $token = $notice->token;

            return true;
        });
        $this->post('/logout');

        return [$admin, $token, $resetRequest];
    }

    private function resetPayload(User $admin, string $token, string $password): array
    {
        return ['token' => $token, 'email' => $admin->email, 'password' => $password, 'password_confirmation' => $password];
    }
}
