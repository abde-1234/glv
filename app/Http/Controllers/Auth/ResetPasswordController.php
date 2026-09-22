<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetRequestEvent;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = DB::transaction(function () use ($credentials): string {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])
                ->lockForUpdate()
                ->first();

            $resetRequest = $user?->passwordResetRequests()
                ->where('status', PasswordResetRequest::STATUS_APPROVED)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($user === null
                || $user->role !== User::ROLE_ADMIN_AGENCE
                || $user->statut !== 'actif'
                || $resetRequest === null
                || $resetRequest->agence_id !== $user->agence_id
                || $resetRequest->expireIfNeeded()) {
                return Password::INVALID_TOKEN;
            }

            return Password::broker()->reset(
                $credentials,
                function (User $brokerUser, string $password) use ($user, $resetRequest): void {
                    abort_unless($brokerUser->is($user), 403);

                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    if (Schema::hasTable(config('session.table', 'sessions'))) {
                        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                    }

                    $resetRequest->forceFill([
                        'status' => PasswordResetRequest::STATUS_COMPLETED,
                        'pending_key' => null,
                    ])->save();

                    $resetRequest->recordEvent(PasswordResetRequestEvent::TYPE_RESET_USED, $user);
                    $resetRequest->recordEvent(PasswordResetRequestEvent::TYPE_PASSWORD_CHANGED, $user);

                    event(new PasswordReset($user));
                }
            );
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Ce lien de réinitialisation est invalide ou a expiré.']);
        }

        return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');
    }
}
