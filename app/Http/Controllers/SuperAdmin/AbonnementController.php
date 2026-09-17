<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\RenewalRequest;
use App\Notifications\GlvNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbonnementController extends Controller
{
    public function index(Request $request): View
    {
        $agences = Agence::query()
            ->with('primaryAdmin')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim($request->string('q')->toString());
                $query->where(fn ($query) => $query
                    ->where('nom', 'like', "%{$search}%")
                    ->orWhere('ville', 'like', "%{$search}%"));
            })
            ->when($request->filled('statut'), fn ($query) => $query->withSubscriptionStatus($request->string('statut')->toString()))
            ->when($request->filled('plan'), fn ($query) => $query->where('type_abonnement', $request->string('plan')->toString()))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $selectedAgence = $request->filled('edit')
            ? Agence::with('primaryAdmin')->find($request->integer('edit'))
            : null;
        $pendingRenewals = RenewalRequest::query()
            ->with(['agence', 'requester'])
            ->where('status', RenewalRequest::PENDING)
            ->oldest()
            ->get();
        $today = today();

        return view('super-admin.abonnements.index', [
            'agences' => $agences,
            'selectedAgence' => $selectedAgence,
            'totalAgencies' => Agence::count(),
            'activeSubscriptions' => Agence::query()->withSubscriptionStatus('actif')->count(),
            'trialSubscriptions' => Agence::query()->withSubscriptionStatus('essai')->count(),
            'inactiveSubscriptions' => Agence::query()->withSubscriptionStatus('expire')->count()
                + Agence::query()->withSubscriptionStatus('suspendu')->count(),
            'plans' => $this->plans(),
            'pendingRenewals' => $pendingRenewals,
            'expiredCount' => Agence::query()->withSubscriptionStatus('expire')->count(),
            'urgentCount' => Agence::query()->whereNotIn('statut', ['suspendu', 'expire'])
                ->whereBetween('date_expiration', [$today, $today->copy()->addDays(7)])->count(),
            'warningCount' => Agence::query()->whereNotIn('statut', ['suspendu', 'expire'])
                ->whereBetween('date_expiration', [$today->copy()->addDays(8), $today->copy()->addDays(30)])->count(),
        ]);
    }

    public function update(Request $request, Agence $agence): RedirectResponse
    {
        $previousExpiration = $agence->date_expiration?->copy();
        $request->mergeIfMissing([
            'type_abonnement' => $agence->type_abonnement,
            'date_debut_abonnement' => $agence->date_debut_abonnement?->format('Y-m-d'),
            'date_expiration' => $agence->date_expiration?->format('Y-m-d'),
            'montant_abonnement' => $agence->montant_abonnement,
            'statut' => $agence->statut,
        ]);

        $validated = $request->validate([
            'type_abonnement' => ['nullable', 'string', 'max:100'],
            'statut' => ['required', Rule::in(['actif', 'essai', 'suspendu', 'expire'])],
            'date_debut_abonnement' => ['nullable', 'date'],
            'date_expiration' => ['nullable', 'date', 'after_or_equal:date_debut_abonnement'],
            'montant_abonnement' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $isRenewal = isset($validated['date_expiration'])
            && ($previousExpiration === null || $agence->date_expiration?->lt($validated['date_expiration']));
        if ($isRenewal && $agence->statut === 'expire' && $validated['statut'] !== 'suspendu') {
            $validated['statut'] = 'actif';
        }

        $agence->update($validated);

        if ($isRenewal) {
            $agence->users()->where('statut', 'actif')->get()->each->notify(new GlvNotification([
                'type' => 'subscription_renewed',
                'title' => 'Abonnement renouvelé',
                'message' => 'Votre abonnement a été renouvelé.',
                'url' => route('agence.settings.subscription'),
                'agency_id' => $agence->id,
            ]));
        }

        return redirect()
            ->route('super-admin.abonnements.index', ['edit' => $agence->id])
            ->with('success', 'Abonnement modifié avec succès.');
    }

    private function plans(): Collection
    {
        return collect(['Essai', 'Basic', 'Pro', 'Premium'])
            ->merge(Agence::query()
                ->whereNotNull('type_abonnement')
                ->distinct()
                ->orderBy('type_abonnement')
                ->pluck('type_abonnement'))
            ->filter()
            ->unique()
            ->values();
    }
}
