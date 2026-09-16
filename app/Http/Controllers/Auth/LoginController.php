<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectFor(Auth::user());
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => "Trop de tentatives. Réessayez dans {$seconds} secondes."]);
        }

        if (! Auth::attempt([...$credentials, 'statut' => 'actif'], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Ces identifiants ne correspondent pas à nos enregistrements.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return $this->redirectFor($request->user());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectFor(User $user): RedirectResponse
    {
        return match ($user->role) {
            User::ROLE_SUPER_ADMIN => redirect()->route('super-admin.dashboard'),
            User::ROLE_ADMIN_AGENCE, User::ROLE_EMPLOYE => redirect()->route('dashboard'),
            default => abort(403),
        };
    }
}
