<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class SuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_are_validated_saved_and_applied(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('super-admin.settings.security'), [
            'timezone' => 'Europe/Paris', 'session_minutes' => 15, 'email_verification' => 1, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['key' => 'timezone', 'value' => 'Europe/Paris']);
        $this->assertDatabaseHas('settings', ['key' => 'session_minutes', 'value' => '15']);
        $this->withSession(['super_admin_last_activity' => time() - 901])
            ->get(route('super-admin.settings.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_management_does_not_change_tenant_roles_and_requires_password(): void
    {
        $admin = $this->superAdmin();
        $payload = ['admin_name' => 'New admin', 'admin_email' => 'new@glv.test', 'admin_password' => 'strong-password-123',
            'admin_password_confirmation' => 'strong-password-123', 'current_password' => 'password', 'role' => 'employe', 'agence_id' => 123];
        $this->actingAs($admin)->post(route('super-admin.settings.admins.store'), $payload)->assertSessionHasNoErrors();
        $created = User::where('email', 'new@glv.test')->firstOrFail();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $created->role);
        $this->assertNull($created->agence_id);
        $this->assertTrue(Hash::check('strong-password-123', $created->password));
        $payload['admin_status'] = 'inactif';
        $this->put(route('super-admin.settings.admins.update', $created), $payload)->assertSessionHasNoErrors();
        $this->assertSame('inactif', $created->refresh()->statut);
        $payload['admin_email'] = $admin->email;
        $this->put(route('super-admin.settings.admins.update', $admin), $payload)->assertStatus(422);
        $tenantUser = User::factory()->create(['role' => User::ROLE_ADMIN_AGENCE]);
        $this->put(route('super-admin.settings.admins.update', $tenantUser), $payload)->assertNotFound();
        $payload['current_password'] = 'incorrect';
        $payload['admin_email'] = 'another@glv.test';
        $this->post(route('super-admin.settings.admins.store'), $payload)->assertSessionHasErrors('current_password');
        $this->assertNull(session('_old_input.admin_password'));
        $this->actingAs($created)->get(route('super-admin.settings.edit'))->assertForbidden();
    }

    public function test_agency_roles_cannot_access_new_settings_routes(): void
    {
        foreach (User::AGENCY_ROLES as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->put(route('super-admin.settings.security'), [])->assertForbidden();
            $this->post(route('super-admin.settings.admins.store'), [])->assertForbidden();
            $this->put(route('super-admin.settings.admins.update', 1), [])->assertForbidden();
        }
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_email_verification_requires_a_signed_link_for_the_current_user(): void
    {
        Mail::fake();
        Setting::create(['key' => 'email_verification', 'value' => '1']);
        $admin = $this->superAdmin();
        $admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($admin)->get(route('super-admin.settings.edit'))->assertRedirect(route('super-admin.verification.notice'));
        $this->get(route('super-admin.verification.notice'))->assertOk();
        $this->post(route('super-admin.verification.send'))->assertSessionHas('success');
        $params = ['id' => $admin->id, 'hash' => sha1($admin->email)];
        $this->get(route('super-admin.verification.verify', $params))->assertForbidden();
        $url = URL::temporarySignedRoute('super-admin.verification.verify', now()->addHour(), $params);
        $this->get($url)->assertRedirect(route('super-admin.settings.edit'));
        $this->assertNotNull($admin->refresh()->email_verified_at);
        $this->get(route('super-admin.settings.edit'))->assertOk();
    }

    public function test_settings_page_and_sidebar_are_available_to_super_admin_only(): void
    {
        $this->get(route('super-admin.settings.edit'))->assertRedirect(route('login'));
        $this->put(route('super-admin.settings.update'), ['section' => 'account'])->assertRedirect(route('login'));

        foreach ([User::ROLE_ADMIN_AGENCE, User::ROLE_EMPLOYE] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('super-admin.settings.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('super-admin.settings.update'), ['section' => 'platform', 'platform_name' => 'Intrusion'])->assertForbidden();
        }

        $this->actingAs($this->superAdmin())->get(route('super-admin.settings.edit'))
            ->assertOk()
            ->assertSee('Informations plateforme')
            ->assertSee('Préférences générales')
            ->assertSee('Compte Super Admin')
            ->assertSee('href="'.route('super-admin.settings.edit').'"', false);
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_platform_information_and_preferences_are_persisted_without_replacing_other_sections(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'platform',
            'platform_name' => 'GLV Location',
            'support_email' => 'support@glv.test',
            'support_phone' => '0612345678',
            'company_address' => 'GLV, Casablanca',
            'default_currency' => 'USD',
            'role' => User::ROLE_EMPLOYE,
        ])->assertRedirect(route('super-admin.settings.edit'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('platform_settings', [
            'id' => 1, 'platform_name' => 'GLV Location', 'support_email' => 'support@glv.test',
            'support_phone' => '0612345678', 'company_address' => 'GLV, Casablanca', 'default_currency' => 'MAD',
        ]);

        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'preferences', 'default_language' => 'en', 'default_currency' => 'EUR',
            'date_format' => 'Y-m-d', 'per_page' => 25,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('platform_settings', 1);
        $this->assertDatabaseHas('platform_settings', [
            'platform_name' => 'GLV Location', 'default_language' => 'en',
            'default_currency' => 'EUR', 'date_format' => 'Y-m-d', 'per_page' => 25,
        ]);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->refresh()->role);
        $this->actingAs($admin)->get(route('super-admin.settings.edit'))->assertSee('GLV Location')->assertSee('support@glv.test');
    }

    public function test_invalid_settings_and_unsafe_logo_files_are_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'platform', 'platform_name' => '', 'support_email' => 'invalid',
            'logo' => UploadedFile::fake()->create('script.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors(['platform_name', 'support_email', 'logo']);
        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'preferences', 'default_language' => 'bad', 'default_currency' => 'BAD',
            'date_format' => 'bad', 'per_page' => 10000,
        ])->assertSessionHasErrors(['default_language', 'default_currency', 'date_format', 'per_page']);
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_logo_upload_replaces_only_the_platform_logo(): void
    {
        Storage::fake('public');
        $settings = PlatformSetting::current();
        $settings->logo = 'platform/logos/old.png';
        $settings->save();
        Storage::disk('public')->put($settings->logo, 'old logo');
        Storage::disk('public')->put('agences/logo.png', 'agency logo');

        $this->actingAs($this->superAdmin())->put(route('super-admin.settings.update'), [
            'section' => 'platform', 'platform_name' => 'GLV',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists($settings->refresh()->logo);
        Storage::disk('public')->assertMissing('platform/logos/old.png');
        Storage::disk('public')->assertExists('agences/logo.png');
    }

    public function test_failed_logo_storage_keeps_the_previous_logo_and_settings(): void
    {
        $disk = Storage::fake('public');
        $settings = PlatformSetting::current();
        $settings->logo = 'platform/logos/previous.png';
        $settings->save();
        $disk->put($settings->logo, 'previous logo');

        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        $failingDisk->shouldNotReceive('delete');
        Storage::set('public', $failingDisk);

        $this->actingAs($this->superAdmin())->from(route('super-admin.settings.edit'))
            ->put(route('super-admin.settings.update'), [
                'section' => 'platform', 'platform_name' => 'Changed name',
                'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')),
            ])->assertRedirect(route('super-admin.settings.edit'))->assertSessionHasErrors('logo');

        $this->assertSame('platform/logos/previous.png', $settings->refresh()->logo);
        $this->assertSame('GLV', $settings->platform_name);
        $disk->assertExists('platform/logos/previous.png');
    }

    public function test_account_edits_only_change_the_authenticated_super_admin_and_validate_email_uniqueness(): void
    {
        $admin = $this->superAdmin();
        $other = $this->superAdmin();
        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'account', 'name' => 'Admin actualisé', 'email' => 'admin-updated@glv.test',
            'user_id' => $other->id, 'role' => User::ROLE_EMPLOYE, 'agence_id' => 123,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Admin actualisé', $admin->refresh()->name);
        $this->assertSame('admin-updated@glv.test', $admin->email);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertNull($admin->agence_id);
        $this->assertNotSame('Admin actualisé', $other->refresh()->name);

        $this->actingAs($admin)->put(route('super-admin.settings.update'), [
            'section' => 'account', 'name' => 'Another name', 'email' => $other->email,
        ])->assertSessionHasErrors('email');
        $this->assertSame('Admin actualisé', $admin->refresh()->name);
    }

    public function test_password_changes_require_the_current_password_and_matching_confirmation(): void
    {
        $admin = $this->superAdmin();
        $payload = ['section' => 'password', 'current_password' => 'incorrect', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];

        $this->actingAs($admin)->put(route('super-admin.settings.update'), $payload)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));

        $payload['current_password'] = 'password';
        $payload['password_confirmation'] = 'different-password';
        $this->actingAs($admin)->put(route('super-admin.settings.update'), $payload)->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));

        $payload['password_confirmation'] = $payload['password'];
        $this->actingAs($admin)->put(route('super-admin.settings.update'), $payload)->assertRedirect(route('super-admin.settings.edit'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $admin->refresh()->password));
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'agence_id' => null]);
    }
}
