<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\AgenceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_five_pages_render_for_the_agency_admin(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence Atlas');

        foreach ([
            route('agence.settings.general'),
            route('agence.settings.contact'),
            route('agence.settings.subscription'),
            route('agence.settings.preferences'),
            route('agence.settings.documents.index'),
            route('agence.settings.users.index'),
            route('agence.profile.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee($agency->nom);
        }
    }

    public function test_admin_only_sees_and_updates_its_own_agency(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');

        $this->actingAs($adminA)
            ->get(route('agence.settings.general'))
            ->assertOk()
            ->assertSee('Agence A')
            ->assertDontSee('Agence B');

        $this->actingAs($adminA)->put(route('agence.settings.general.update'), [
            'agence_id' => $agencyB->id,
            'nom' => 'Agence A modifiée',
            'description' => 'Description locale',
            'statut' => 'suspendu',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Agence A modifiée', $agencyA->refresh()->nom);
        $this->assertSame('actif', $agencyA->statut);
        $this->assertSame('Agence B', $agencyB->refresh()->nom);
    }

    public function test_logo_upload_replaces_only_the_current_agency_logo(): void
    {
        Storage::fake('public');
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A', ['logo' => 'agences/old-a.png']);
        [$agencyB] = $this->createAgencyAdmin('Agence B', ['logo' => 'agences/b.png']);
        Storage::disk('public')->put('agences/old-a.png', 'old');
        Storage::disk('public')->put('agences/b.png', 'other');

        $this->actingAs($adminA)->put(route('agence.settings.general.update'), [
            'agence_id' => $agencyB->id,
            'nom' => $agencyA->nom,
            'logo' => $this->uploadedPng('new-logo.png'),
        ])->assertSessionHasNoErrors();

        $agencyA->refresh();
        Storage::disk('public')->assertExists($agencyA->logo);
        Storage::disk('public')->assertMissing('agences/old-a.png');
        Storage::disk('public')->assertExists('agences/b.png');
        $this->assertSame('agences/b.png', $agencyB->refresh()->logo);
    }

    public function test_contact_and_preferences_are_validated_and_saved(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');

        $this->actingAs($admin)->put(route('agence.settings.contact.update'), [
            'adresse' => '12 Avenue Hassan II',
            'ville' => 'Fès',
            'code_postal' => '30000',
            'pays' => 'Maroc',
            'telephone' => '06 12 34 56 78',
            'email' => 'contact@agence.test',
            'site_web' => 'https://agence.test',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->actingAs($admin)->put(route('agence.settings.preferences.update'), [
            'devise' => 'EUR',
            'format_date' => 'Y-m-d',
            'langue' => 'fr',
            'elements_par_page' => 25,
            'notifications_email' => '1',
            'rapport_mensuel' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $agency->refresh();
        $this->assertSame('Fès', $agency->ville);
        $this->assertSame('https://agence.test', $agency->site_web);
        $this->assertSame('EUR', $agency->devise);
        $this->assertSame(25, $agency->elements_par_page);
        $this->assertTrue($agency->notifications_email);
        $this->assertFalse($agency->rappel_reservation);
        $this->assertTrue($agency->rapport_mensuel);
    }

    public function test_subscription_is_read_only_and_uses_database_values(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A', [
            'type_abonnement' => 'Entreprise',
            'date_expiration' => today()->addDays(18),
        ]);

        $this->actingAs($admin)
            ->get(route('agence.settings.subscription'))
            ->assertOk()
            ->assertSee('Entreprise')
            ->assertSee('18 jour(s)')
            ->assertDontSee('299 MAD');

        $this->actingAs($admin)->put('/parametres/abonnement', [
            'type_abonnement' => 'Intrusion',
        ])->assertStatus(405);

        $this->assertSame('Entreprise', $agency->refresh()->type_abonnement);
    }

    public function test_document_upload_download_delete_and_tenant_isolation(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB] = $this->createAgencyAdmin('Agence B');

        $this->actingAs($adminA)->post(route('agence.settings.documents.store'), [
            'agence_id' => $agencyB->id,
            'nom' => 'Registre A',
            'type' => 'registre_commerce',
            'fichier' => UploadedFile::fake()->createWithContent('registre.pdf', "%PDF-1.4\nTask five"),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $documentA = AgenceDocument::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($agencyA->id, $documentA->agence_id);
        Storage::disk('documents')->assertExists($documentA->fichier);
        Storage::disk('public')->assertMissing($documentA->fichier);

        $foreignPath = "agences/{$agencyB->id}/documents/secret.pdf";
        Storage::disk('documents')->put($foreignPath, '%PDF foreign');
        $documentB = AgenceDocument::withoutEvents(fn () => AgenceDocument::withoutGlobalScopes()->create([
            'agence_id' => $agencyB->id,
            'nom' => 'Secret B',
            'type' => 'autre',
            'fichier' => $foreignPath,
            'taille' => 20,
        ]));

        $this->actingAs($adminA)->get(route('agence.settings.documents.index'))->assertOk()->assertSee('Registre A')->assertDontSee('Secret B');
        $this->actingAs($adminA)->get(route('agence.settings.documents.download', $documentA))->assertOk();
        $this->actingAs($adminA)->get(route('agence.settings.documents.download', $documentB))->assertForbidden();
        $this->actingAs($adminA)->delete(route('agence.settings.documents.destroy', $documentB))->assertForbidden();
        $this->actingAs($adminA)->delete(route('agence.settings.documents.destroy', $documentA))->assertSessionHas('success');

        Storage::disk('documents')->assertMissing($documentA->fichier);
        Storage::disk('documents')->assertExists($foreignPath);
    }

    public function test_user_creation_forces_agency_hashes_password_and_foreign_users_are_forbidden(): void
    {
        [$agencyA, $adminA] = $this->createAgencyAdmin('Agence A');
        [$agencyB, $adminB] = $this->createAgencyAdmin('Agence B');

        $this->actingAs($adminA)->post(route('agence.settings.users.store'), [
            'agence_id' => $agencyB->id,
            'name' => 'Employé A',
            'email' => 'employe-a@glv.test',
            'telephone' => '0612345678',
            'password' => 'temporary-password',
            'role' => User::ROLE_EMPLOYE,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $employee = User::where('email', 'employe-a@glv.test')->firstOrFail();
        $this->assertSame($agencyA->id, $employee->agence_id);
        $this->assertSame(User::ROLE_EMPLOYE, $employee->role);
        $this->assertTrue(Hash::check('temporary-password', $employee->password));

        $this->actingAs($adminA)->put(route('agence.settings.users.update', $adminB), [])->assertForbidden();
        $this->actingAs($adminA)->delete(route('agence.settings.users.destroy', $adminB))->assertForbidden();
        $this->actingAs($adminA)->get(route('agence.settings.users.index'))->assertOk()->assertSee('Employé A')->assertDontSee($adminB->email);
    }

    public function test_user_can_be_updated_without_replacing_an_empty_password_and_self_delete_is_blocked(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $employee = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_EMPLOYE,
            'statut' => 'actif',
            'password' => 'original-password',
        ]);
        $oldPassword = $employee->password;

        $this->actingAs($admin)->put(route('agence.settings.users.update', $employee), [
            'name' => 'Employé modifié',
            'email' => $employee->email,
            'telephone' => null,
            'password' => null,
            'role' => User::ROLE_EMPLOYE,
            'statut' => 'inactif',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $employee->refresh();
        $this->assertSame('Employé modifié', $employee->name);
        $this->assertSame('inactif', $employee->statut);
        $this->assertSame($oldPassword, $employee->password);

        $this->actingAs($admin)->delete(route('agence.settings.users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_profile_and_password_can_be_updated(): void
    {
        [, $admin] = $this->createAgencyAdmin('Agence A');

        $this->actingAs($admin)->put(route('agence.profile.update'), [
            'name' => 'Imane Idrissi',
            'email' => 'imane@glv.test',
            'telephone' => '06 12 34 56 78',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->actingAs($admin)->put(route('agence.profile.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('agence.profile.edit', ['section' => 'password']))->assertSessionHas('success');

        $admin->refresh();
        $this->assertSame('Imane Idrissi', $admin->name);
        $this->assertTrue(Hash::check('new-password-123', $admin->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        [, $admin] = $this->createAgencyAdmin('Agence A');
        $oldHash = $admin->password;

        $this->actingAs($admin)->from(route('agence.profile.edit', ['section' => 'password']))->put(route('agence.profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($oldHash, $admin->refresh()->password);
    }

    public function test_employee_can_log_in_use_agency_space_and_profile_but_not_agency_settings(): void
    {
        [$agency] = $this->createAgencyAdmin('Agence A');
        $employee = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_EMPLOYE,
            'statut' => 'actif',
            'password' => 'password',
        ]);

        $this->post('/login', ['email' => $employee->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('agence.profile.edit'))->assertOk();
        $this->get(route('agence.settings.general'))->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in_and_super_admin_cannot_access_task_five(): void
    {
        [$agency] = $this->createAgencyAdmin('Agence A');
        $inactive = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_EMPLOYE,
            'statut' => 'inactif',
            'password' => 'password',
        ]);
        $superAdmin = User::factory()->create(['agence_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);

        $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($superAdmin)->get(route('agence.settings.general'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('agence.profile.edit'))->assertForbidden();
    }

    public function test_invalid_user_edit_reopens_its_dialog_with_values_and_errors(): void
    {
        [$agency, $admin] = $this->createAgencyAdmin('Agence A');
        $employee = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_EMPLOYE,
            'statut' => 'actif',
        ]);
        $url = route('agence.settings.users.index');

        $this->actingAs($admin)->from($url)->put(route('agence.settings.users.update', $employee), [
            '_user_form' => 'edit-'.$employee->id,
            'name' => 'Nom conservé',
            'email' => $admin->email,
            'telephone' => '0611223344',
            'role' => User::ROLE_ADMIN_AGENCE,
            'statut' => 'inactif',
            'password' => '',
        ])->assertRedirect($url)->assertSessionHasErrors('email');

        $response = $this->get($url)->assertOk();
        $dialog = (string) str($response->getContent())
            ->after('id="edit-agency-user-'.$employee->id.'"')
            ->before('</dialog>');

        $this->assertStringContainsString('data-auto-open', $dialog);
        $this->assertStringContainsString('value="Nom conservé"', $dialog);
        $this->assertStringContainsString('value="0611223344"', $dialog);
        $this->assertStringContainsString('value="admin_agence" selected', $dialog);
        $this->assertStringContainsString('value="inactif" selected', $dialog);
        $this->assertStringContainsString('role="alert"', $dialog);
        $this->assertSame(1, substr_count($response->getContent(), ' data-auto-open'));
        $this->assertSame(User::ROLE_EMPLOYE, $employee->refresh()->role);
        $this->assertSame('actif', $employee->statut);
    }

    public function test_profile_shows_both_forms_and_settings_remain_separate(): void
    {
        [, $admin] = $this->createAgencyAdmin('Agence A');

        $this->actingAs($admin)->get(route('agence.profile.edit'))
            ->assertOk()
            ->assertSee('action="'.route('agence.profile.update').'"', false)
            ->assertSee('action="'.route('agence.profile.password').'"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false);

        $this->get(route('agence.settings.general'))
            ->assertOk()
            ->assertSee('agency-settings-content')
            ->assertSee('width="80" height="64"', false)
            ->assertDontSee('name="statut"', false)
            ->assertDontSee('name="agence_id"', false);
    }

    private function createAgencyAdmin(string $name, array $attributes = []): array
    {
        $agency = Agence::create(array_merge([
            'nom' => $name,
            'email' => 'contact@'.str($name)->slug().'.test',
            'telephone' => '0612345678',
            'ville' => 'Fès',
            'adresse' => '12 Avenue Hassan II',
            'statut' => 'actif',
            'date_expiration' => today()->addYear(),
        ], $attributes));
        $admin = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_ADMIN_AGENCE,
            'statut' => 'actif',
            'password' => 'password',
        ]);

        return [$agency, $admin];
    }

    private function uploadedPng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
