<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\RenewalRequest;
use App\Notifications\GlvNotification;
use App\Services\SubscriptionNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RenewalRequestController extends Controller
{
    public function store(Request $request, SubscriptionNotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $agency = $user->agence;
        abort_if($agency === null, 403);

        $validated = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);

        try {
            $renewal = new RenewalRequest(['message' => $validated['message'] ?? null]);
            $renewal->forceFill([
                'agence_id' => $agency->id,
                'requested_by' => $user->id,
                'status' => RenewalRequest::PENDING,
                'pending_key' => $agency->id,
                'current_plan' => $agency->type_abonnement,
                'current_expiration' => $agency->date_expiration,
            ])->save();
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                return back()->with('warning', 'Une demande de renouvellement est déjà en cours.');
            }

            throw $exception;
        }

        $user->notify(new GlvNotification([
            'type' => 'renewal_requested',
            'title' => 'Demande envoyée',
            'message' => 'Votre demande de renouvellement a été envoyée.',
            'url' => route('agence.settings.subscription'),
            'agency_id' => $agency->id,
        ]));
        $notifications->notifySuperAdmins([
            'type' => 'renewal_requested',
            'title' => 'Nouvelle demande de renouvellement',
            'message' => $agency->nom.' demande le renouvellement de son abonnement.',
            'url' => route('super-admin.abonnements.index', ['renewal' => $renewal->id]),
            'agency_id' => $agency->id,
            'renewal_request_id' => $renewal->id,
        ]);

        return back()->with('success', 'Votre demande de renouvellement a été envoyée au Super Admin.');
    }
}
