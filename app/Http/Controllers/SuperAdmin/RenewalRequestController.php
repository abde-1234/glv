<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\RenewalRequest;
use App\Notifications\GlvNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RenewalRequestController extends Controller
{
    public function update(Request $request, string $agence): RedirectResponse
    {
        $renewalRequest = RenewalRequest::query()->findOrFail($agence);
        abort_unless($renewalRequest->status === RenewalRequest::PENDING, 422);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([RenewalRequest::APPROVED, RenewalRequest::REJECTED])],
            'decision_message' => ['nullable', 'string', 'max:2000'],
            'type_abonnement' => ['required_if:decision,approved', 'nullable', 'string', 'max:100'],
            'date_debut_abonnement' => ['required_if:decision,approved', 'nullable', 'date'],
            'date_expiration' => ['required_if:decision,approved', 'nullable', 'date', 'after_or_equal:date_debut_abonnement', 'after_or_equal:today'],
            'montant_abonnement' => ['required_if:decision,approved', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($request, $renewalRequest, $validated): void {
            $renewalRequest = RenewalRequest::query()->lockForUpdate()->findOrFail($renewalRequest->id);
            abort_unless($renewalRequest->status === RenewalRequest::PENDING, 422);
            $agency = $renewalRequest->agence()->lockForUpdate()->firstOrFail();

            if ($validated['decision'] === RenewalRequest::APPROVED) {
                $agency->update([
                    'type_abonnement' => $validated['type_abonnement'],
                    'date_debut_abonnement' => $validated['date_debut_abonnement'],
                    'date_expiration' => $validated['date_expiration'],
                    'montant_abonnement' => $validated['montant_abonnement'],
                    'statut' => $agency->isSubscriptionSuspended() ? 'suspendu' : 'actif',
                ]);
            }

            $renewalRequest->forceFill([
                'status' => $validated['decision'],
                'pending_key' => null,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'decision_message' => $validated['decision_message'] ?? null,
            ])->save();
        });

        $approved = $validated['decision'] === RenewalRequest::APPROVED;
        $decisionMessage = trim((string) ($validated['decision_message'] ?? ''));
        $notificationMessage = $approved
            ? 'Votre abonnement a été renouvelé avec succès.'
            : 'Votre demande de renouvellement a été refusée.';
        if ($decisionMessage !== '') {
            $notificationMessage .= ($approved ? ' Note : ' : ' Motif : ').$decisionMessage;
        }
        $renewalRequest->agence->users()->where('statut', 'actif')->get()->each->notify(new GlvNotification([
            'type' => $approved ? 'renewal_approved' : 'renewal_rejected',
            'title' => $approved ? 'Renouvellement approuvé' : 'Demande refusée',
            'message' => $notificationMessage,
            'url' => route('agence.settings.subscription'),
            'agency_id' => $renewalRequest->agence_id,
        ]));

        return redirect()->route('super-admin.abonnements.index')
            ->with('success', $approved ? 'Abonnement renouvelé avec succès.' : 'Demande de renouvellement refusée.');
    }
}
