<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetRequestEvent;
use App\Models\User;
use App\Notifications\AgencyPasswordResetLink;
use App\Notifications\AgencyPasswordResetRejected;
use App\Notifications\GlvNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PasswordResetRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'completed', 'expired'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $this->expireStaleRequests();

        $requests = PasswordResetRequest::query()
            ->with(['agence', 'user', 'processor'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('agence', fn ($query) => $query->where('nom', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();

        return view('super-admin.password-resets.index', compact('requests'));
    }

    public function show(string $agence): View
    {
        $resetRequest = PasswordResetRequest::query()->findOrFail($agence);
        $resetRequest->expireIfNeeded();

        return view('super-admin.password-resets.show', [
            'resetRequest' => $resetRequest->load(['agence', 'user', 'processor', 'events.actor']),
        ]);
    }

    public function approve(Request $request, string $agence): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $agence): string {
            $resetRequest = PasswordResetRequest::query()->lockForUpdate()->findOrFail($agence);

            if ($resetRequest->expireIfNeeded() || $resetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
                return 'invalid';
            }

            $user = $resetRequest->user;
            if ($user->role !== User::ROLE_ADMIN_AGENCE
                || $user->statut !== 'actif'
                || $user->agence_id !== $resetRequest->agence_id) {
                return 'invalid';
            }

            $token = Password::broker()->createToken($user);
            $resetRequest->forceFill([
                'status' => PasswordResetRequest::STATUS_APPROVED,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'expires_at' => now()->addMinutes((int) config('auth.passwords.users.expire', 60)),
            ])->save();

            $resetRequest->recordEvent(PasswordResetRequestEvent::TYPE_APPROVED, $request->user());

            $user->notify((new AgencyPasswordResetLink($token))->locale($user->agence?->langue ?? 'fr'));
            $user->notify(new GlvNotification([
                'type' => 'password_reset_approved',
                'title' => 'Réinitialisation approuvée',
                'message' => 'Votre demande de réinitialisation a été traitée. Votre nouvel accès est disponible selon la procédure définie.',
                'url' => route('login'),
            ]));

            return 'approved';
        });

        return $result === 'approved'
            ? back()->with('success', 'La demande a été approuvée et le lien sécurisé a été envoyé.')
            : back()->withErrors(['request' => 'Cette demande a déjà été traitée ou a expiré.']);
    }

    public function reject(Request $request, string $agence): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], ['rejection_reason.required' => 'Veuillez indiquer le motif du refus.']);

        $result = DB::transaction(function () use ($request, $agence, $validated): string {
            $resetRequest = PasswordResetRequest::query()->lockForUpdate()->findOrFail($agence);

            if ($resetRequest->expireIfNeeded() || $resetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
                return 'invalid';
            }

            $resetRequest->forceFill([
                'status' => PasswordResetRequest::STATUS_REJECTED,
                'pending_key' => null,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ])->save();

            $resetRequest->recordEvent(PasswordResetRequestEvent::TYPE_REJECTED, $request->user(), [
                'reason' => $validated['rejection_reason'],
            ]);

            $resetRequest->user->notify(new GlvNotification([
                'type' => 'password_reset_rejected',
                'title' => 'Demande de réinitialisation refusée',
                'message' => 'Votre demande de réinitialisation a été refusée par le Super Admin. Motif : '.$validated['rejection_reason'],
                'url' => route('login'),
            ]));
            $resetRequest->user->notify(
                (new AgencyPasswordResetRejected($validated['rejection_reason']))
                    ->locale($resetRequest->user->agence?->langue ?? 'fr')
            );

            return 'rejected';
        });

        return $result === 'rejected'
            ? back()->with('success', 'La demande a été refusée et le demandeur a été informé.')
            : back()->withErrors(['request' => 'Cette demande a déjà été traitée ou a expiré.']);
    }

    private function expireStaleRequests(): void
    {
        PasswordResetRequest::query()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lazyById(100)
            ->each(fn (PasswordResetRequest $resetRequest) => $resetRequest->expireIfNeeded());
    }
}
