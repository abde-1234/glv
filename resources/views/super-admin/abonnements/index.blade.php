@extends('layouts.super-admin')

@section('title', 'Abonnements')

@section('content')
    <div class="page-heading">
        <div><p class="page-eyebrow">Pilotage commercial</p><h1>Abonnements</h1><p>Gérez manuellement les plans et échéances de toutes les agences.</p></div>
        <a class="primary-button" href="{{ route('super-admin.agences.create') }}"><x-icon name="plus" /> Nouvelle agence</a>
    </div>

    <section class="kpi-grid agency-kpis" aria-label="Statistiques des abonnements">
        <article class="kpi-card"><span class="kpi-icon blue"><x-icon name="building" /></span><div><p>Agences au total</p><strong>{{ $totalAgencies }}</strong><small>toutes structures</small></div></article>
        <article class="kpi-card"><span class="kpi-icon green"><x-icon name="check" /></span><div><p>Abonnements actifs</p><strong>{{ $activeSubscriptions }}</strong><small>valides aujourd’hui</small></div></article>
        <article class="kpi-card"><span class="kpi-icon orange"><x-icon name="clock" /></span><div><p>En essai</p><strong>{{ $trialSubscriptions }}</strong><small>essais encore valides</small></div></article>
        <article class="kpi-card"><span class="kpi-icon red"><x-icon name="pause" /></span><div><p>Expirés / suspendus</p><strong>{{ $inactiveSubscriptions }}</strong><small>à régulariser</small></div></article>
    </section>

    <section class="subscription-attention" aria-label="Abonnements nécessitant votre attention">
        <header><div><p class="page-eyebrow">Alertes</p><h2>Abonnements nécessitant votre attention</h2></div></header>
        <div>
            <article class="is-danger"><strong>{{ $expiredCount }}</strong><span>abonnement(s) expiré(s)</span></article>
            <article class="is-urgent"><strong>{{ $urgentCount }}</strong><span>expire(nt) dans 7 jours</span></article>
            <article class="is-warning"><strong>{{ $warningCount }}</strong><span>expire(nt) dans 30 jours</span></article>
            <article class="is-info"><strong>{{ $pendingRenewals->count() }}</strong><span>demande(s) en attente</span></article>
        </div>
    </section>

    <section @class(['subscriptions-layout', 'has-details' => $selectedAgence])>
        <article class="content-card list-card subscriptions-list">
            <form class="filter-bar" method="GET" action="{{ route('super-admin.abonnements.index') }}">
                <label class="filter-search"><x-icon name="search" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher une agence…" aria-label="Rechercher"></label>
                <label><span class="sr-only">Statut</span><select name="statut"><option value="">Tous les statuts</option>@foreach (['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'] as $value => $label)<option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="sr-only">Plan</span><select name="plan"><option value="">Tous les plans</option>@foreach ($plans as $plan)<option value="{{ $plan }}" @selected(request('plan') === $plan)>{{ $plan }}</option>@endforeach</select></label>
                <button class="filter-button" type="submit">Filtrer</button>
                @if (request()->hasAny(['q', 'statut', 'plan']))<a class="reset-filter" href="{{ route('super-admin.abonnements.index') }}">Réinitialiser</a>@endif
            </form>

            <div class="table-scroll">
                <table class="data-table subscriptions-table">
                    <thead><tr><th>Agence</th><th>Plan</th><th>Statut</th><th>Date début</th><th>Date fin</th><th>Jours restants</th><th>Montant</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse ($agences as $agence)
                        @php
                            $displayStatus = $agence->subscriptionStatus();
                            $days = $agence->subscriptionDaysRemaining();
                            $daysClass = $days === null ? 'neutral' : ($displayStatus === 'expire' ? 'expired' : ($days <= 7 ? 'warning' : 'valid'));
                        @endphp
                        <tr @class(['selected-row' => $selectedAgence?->is($agence)])>
                            <td><div class="agency-cell"><span class="agency-avatar">{{ str($agence->nom)->substr(0, 2)->upper() }}</span><span><strong>{{ $agence->nom }}</strong><small>{{ $agence->ville ?: '—' }}</small></span></div></td>
                            <td><span class="plan-badge">{{ $agence->type_abonnement ?: 'Non défini' }}</span></td>
                            <td><span class="status-badge {{ $displayStatus }}"><i></i>{{ ['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'][$displayStatus] }}</span></td>
                            <td>{{ $agence->date_debut_abonnement?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $agence->date_expiration?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="days-badge {{ $daysClass }}">{{ $days === null ? 'Sans échéance' : $days.' jours' }}</span></td>
                            <td>{{ $agence->montant_abonnement === null ? '—' : number_format((float) $agence->montant_abonnement, 2, ',', ' ').' MAD' }}</td>
                            <td><a class="table-edit-button" href="{{ route('super-admin.abonnements.index', array_merge(request()->except('page'), ['edit' => $agence->id])) }}"><x-icon name="edit" /> Voir / Modifier / Renouveler</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>Aucun abonnement trouvé</strong><span>Essayez avec d’autres filtres.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($agences->hasPages())<div class="pagination-wrap">{{ $agences->links() }}</div>@endif
        </article>

        @if ($selectedAgence)
            @php
                $selectedStatus = $selectedAgence->subscriptionStatus();
                $selectedDays = $selectedAgence->subscriptionDaysRemaining();
            @endphp
            <aside class="content-card subscription-details">
                <div class="card-heading">
                    <div><span class="section-icon"><x-icon name="card" /></span><h2>Modifier l’abonnement</h2></div>
                    <a class="icon-link" href="{{ route('super-admin.abonnements.index', request()->except('edit')) }}" aria-label="Fermer"><x-icon name="close" /></a>
                </div>
                <div class="selected-agency-head"><span class="agency-avatar large">{{ str($selectedAgence->nom)->substr(0, 2)->upper() }}</span><span><strong>{{ $selectedAgence->nom }}</strong><small>{{ $selectedAgence->ville ?: 'Ville non renseignée' }} · {{ $selectedAgence->primaryAdmin?->name ?? 'Gérant non renseigné' }}</small></span></div>
                <dl class="detail-list compact-details subscription-current-state">
                    <div><dt>Statut effectif</dt><dd><span class="status-badge {{ $selectedStatus }}"><i></i>{{ ['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'][$selectedStatus] }}</span></dd></div>
                    <div><dt>Jours restants</dt><dd>{{ $selectedDays === null ? 'Sans échéance' : $selectedDays.' jours' }}</dd></div>
                </dl>
                <form method="POST" action="{{ route('super-admin.abonnements.update', $selectedAgence) }}" class="subscription-form">
                    @csrf @method('PATCH')
                    <div class="subscription-form-grid">
                        <label class="form-field full"><span>Plan</span><select name="type_abonnement"><option value="">Non défini</option>@foreach ($plans as $plan)<option value="{{ $plan }}" @selected(old('type_abonnement', $selectedAgence->type_abonnement) === $plan)>{{ $plan }}</option>@endforeach</select>@error('type_abonnement')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field full"><span>Statut <b>*</b></span><select name="statut" required>@foreach (['essai' => 'Essai', 'actif' => 'Actif', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'] as $value => $label)<option value="{{ $value }}" @selected(old('statut', $selectedAgence->statut) === $value)>{{ $label }}</option>@endforeach</select>@error('statut')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Date de début</span><input type="date" name="date_debut_abonnement" value="{{ old('date_debut_abonnement', $selectedAgence->date_debut_abonnement?->format('Y-m-d')) }}">@error('date_debut_abonnement')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Date d’expiration</span><input type="date" name="date_expiration" value="{{ old('date_expiration', $selectedAgence->date_expiration?->format('Y-m-d')) }}">@error('date_expiration')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field full"><span>Montant (MAD)</span><input type="number" name="montant_abonnement" min="0" step="0.01" value="{{ old('montant_abonnement', $selectedAgence->montant_abonnement) }}" placeholder="0,00">@error('montant_abonnement')<small>{{ $message }}</small>@enderror</label>
                    </div>
                    <div class="subscription-form-actions">
                        <a class="secondary-button" href="{{ route('super-admin.abonnements.index', request()->except('edit')) }}">Annuler</a>
                        <button class="primary-button" type="submit"><x-icon name="check" /> Enregistrer les modifications</button>
                    </div>
                </form>
                <div class="tip-box"><x-icon name="info" /><span><strong>Gestion manuelle</strong><small>Aucun paiement automatique n’est déclenché.</small></span></div>
            </aside>
        @endif
    </section>

    <section class="content-card renewal-admin-list" id="demandes-renouvellement">
        <div class="card-heading"><div><span class="section-icon"><x-icon name="mail" /></span><h2>Demandes de renouvellement</h2></div></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Agence</th><th>Demandeur</th><th>Message</th><th>Plan actuel</th><th>Expiration</th><th>Date demande</th><th>Statut</th><th>Traitement</th></tr></thead>
                <tbody>
                @forelse ($pendingRenewals as $renewal)
                    <tr>
                        <td><strong>{{ $renewal->agence->nom }}</strong></td><td>{{ $renewal->requester->name }}</td><td>{{ $renewal->message ?: '—' }}</td><td>{{ $renewal->current_plan ?: '—' }}</td><td>{{ $renewal->current_expiration?->format('d/m/Y') ?? '—' }}</td><td>{{ $renewal->created_at->format('d/m/Y H:i') }}</td><td><span class="renewal-status is-pending">En attente</span></td>
                        <td>
                            <details class="renewal-process"><summary>Traiter</summary>
                                <form method="POST" action="{{ route('super-admin.renewals.update', $renewal) }}">
                                    @csrf @method('PATCH')
                                    <label>Plan<select name="type_abonnement">@foreach ($plans as $plan)<option value="{{ $plan }}" @selected($renewal->current_plan === $plan)>{{ $plan }}</option>@endforeach</select></label>
                                    <label>Début<input type="date" name="date_debut_abonnement" value="{{ today()->format('Y-m-d') }}"></label>
                                    <label>Fin<input type="date" name="date_expiration" value="{{ today()->addYear()->format('Y-m-d') }}"></label>
                                    <label>Montant<input type="number" name="montant_abonnement" min="0" step="0.01" value="{{ $renewal->agence->montant_abonnement }}"></label>
                                    <label class="full">Décision<textarea name="decision_message" rows="2"></textarea></label>
                                    <div><button class="primary-button" name="decision" value="approved" type="submit">Accepter et renouveler</button><button class="danger-button" name="decision" value="rejected" type="submit">Refuser</button></div>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state">Aucune demande en attente.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
