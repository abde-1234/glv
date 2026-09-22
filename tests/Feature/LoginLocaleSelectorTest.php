<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLocaleSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_french_and_ltr_by_default(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="fr" dir="ltr">', false)
            ->assertSee('Connexion')
            ->assertSee('Accédez à votre espace GLV')
            ;
    }

    public function test_language_menu_is_absent_from_login_but_remains_on_other_auth_pages(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('data-language-selector', false)
            ->assertDontSee('data-language-trigger', false)
            ->assertDontSee('class="panel-topbar"', false);

        $this->get('/mot-de-passe-oublie')
            ->assertOk()
            ->assertSee('data-language-selector', false)
            ->assertSee('data-language-trigger', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('role="menu"', false)
            ->assertSee('role="menuitem"', false)
            ->assertSee('Français')
            ->assertSee('English')
            ->assertSee('العربية');
    }

    public function test_english_selection_is_persisted_after_refresh(): void
    {
        $this->from('/login')->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'en');

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Login')
            ->assertSee('Access your GLV workspace')
            ->assertSee('Remember me')
            ->assertSee('Forgot password?')
            ->assertSee('Sign in');
    }

    public function test_arabic_selection_enables_rtl_and_translates_login(): void
    {
        $this->from('/login')->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'ar');

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('تسجيل الدخول')
            ->assertSee('الوصول إلى مساحة GLV الخاصة بك')
            ->assertSee('البريد الإلكتروني')
            ->assertSee('كلمة المرور')
            ->assertSee('تذكرني')
            ->assertSee('هل نسيت كلمة المرور؟');
    }

    public function test_locale_persists_across_auth_pages_and_can_return_to_french(): void
    {
        $this->from('/login')->post(route('locale.update'), ['locale' => 'en']);
        $this->get('/mot-de-passe-oublie')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Forgot your password?')
            ->assertSee('Send request');
        $this->get('/login')->assertSee('Access your GLV workspace');

        $this->from('/login')->post(route('locale.update'), ['locale' => 'ar']);
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'admin@example.test']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('كلمة مرور جديدة')
            ->assertSee('حفظ كلمة المرور الجديدة');

        $this->from('/login')->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'fr');
        $this->get('/login')->assertSee('Accédez à votre espace GLV');
    }

    public function test_invalid_locale_is_rejected_without_changing_session(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->from('/login')
            ->post(route('locale.update'), ['locale' => 'de'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'fr');
    }

    public function test_locale_change_never_redirects_to_an_external_referer(): void
    {
        $this->withHeader('referer', 'https://attacker.example/phishing')
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'en');
    }

    public function test_locale_change_is_csrf_protected(): void
    {
        $this->app->instance('env', 'local');

        try {
            $this->post('/langue', ['locale' => 'en'])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_login_still_works_after_language_change(): void
    {
        $agency = Agence::create(['nom' => 'Locale Agency', 'statut' => 'actif', 'date_expiration' => today()->addYear()]);
        $admin = User::factory()->create([
            'agence_id' => $agency->id,
            'role' => User::ROLE_ADMIN_AGENCE,
            'statut' => 'actif',
            'password' => 'password',
        ]);

        $this->from('/login')->post(route('locale.update'), ['locale' => 'en']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertSame('en', session('locale'));
    }

    public function test_validation_password_toggle_and_forgot_link_remain_available(): void
    {
        $this->post('/login', ['email' => 'invalid', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk();
    }

    public function test_frontend_contains_escape_outside_click_and_keyboard_navigation_handlers(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $script);
        $this->assertStringContainsString("event.key === 'ArrowUp'", $script);
        $this->assertStringContainsString("event.key === 'Home'", $script);
        $this->assertStringContainsString('!selector.contains(event.target)', $script);
    }
}
