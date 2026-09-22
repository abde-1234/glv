<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetRequestEvent;
use App\Models\User;
use App\Notifications\GlvNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public const GENERIC_MESSAGE = 'Votre demande a été envoyée au Super Admin. Vous serez informé lorsque votre accès aura été réinitialisé.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
                ->lockForUpdate()
                ->first();

            if ($user === null
                || $user->role !== User::ROLE_ADMIN_AGENCE
                || $user->statut !== 'actif'
                || $user->agence_id === null) {
                return;
            }

            $existing = PasswordResetRequest::query()
                ->where('user_id', $user->id)
                ->active()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null && ! $existing->expireIfNeeded()) {
                return;
            }

            $resetRequest = PasswordResetRequest::create([
                'agence_id' => $user->agence_id,
                'user_id' => $user->id,
                'status' => PasswordResetRequest::STATUS_PENDING,
                'pending_key' => $user->id,
                'requested_at' => now(),
                'expires_at' => now()->addDay(),
            ]);

            $resetRequest->recordEvent(PasswordResetRequestEvent::TYPE_CREATED, $user);

            $user->notify(new GlvNotification([
                'type' => 'password_reset_submitted',
                'title' => 'Demande envoyée',
                'message' => 'Votre demande de réinitialisation a été transmise au Super Admin.',
                'url' => route('login'),
            ]));

            User::query()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->where('statut', 'actif')
                ->each(fn (User $admin) => $admin->notify(new GlvNotification([
                    'type' => 'password_reset_request',
                    'title' => 'Nouvelle demande de réinitialisation',
                    'message' => "L’administrateur de {$user->agence->nom} demande la réinitialisation de son mot de passe.",
                    'url' => route('super-admin.password-resets.show', $resetRequest),
                ])));
        });

        return back()->with('status', self::GENERIC_MESSAGE);
    }
}
