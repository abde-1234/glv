@extends('layouts.agence')
@section('title', 'Gestion des réservations')
@section('content')
    <header class="agency-page-heading"><div><h1>Gestion des réservations</h1><p>Gérez les réservations de votre agence.</p></div><a class="agency-primary-button" href="{{ route('agence.reservations.create') }}"><x-icon name="plus" /> Nouvelle réservation</a></header>

    <section class="vehicle-kpi-grid" aria-label="Statistiques des réservations">
        <article class="vehicle-kpi"><span class="blue"><x-icon name="calendar" /></span><div><p>Total réservations</p><strong>{{ $stats['total'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="green"><x-icon name="check" /></span><div><p>Confirmées</p><strong>{{ $stats['confirmee'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="cyan"><x-icon name="clock" /></span><div><p>En cours</p><strong>{{ $stats['en_cours'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="orange"><x-icon name="card" /></span><div><p>Terminées</p><strong>{{ $stats['terminee'] }}</strong></div></article>
    </section>

    <section class="agency-card record-list-card">
        <form class="agency-record-filters reservation-filters" method="GET" action="{{ route('agence.reservations.index') }}">
            <label class="record-search"><x-icon name="search" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher référence, client, voiture…" aria-label="Rechercher une réservation"></label>
            <label><span>Statut</span><select name="statut"><option value="">Tous</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Date début</span><input type="date" name="date_debut" value="{{ request('date_debut') }}"></label>
            <label><span>Date fin</span><input type="date" name="date_fin" value="{{ request('date_fin') }}"></label>
            <button class="agency-filter-button" type="submit">Filtrer</button><a class="agency-reset-button" href="{{ route('agence.reservations.index') }}">Réinitialiser</a>
        </form>
        <div class="agency-table-scroll"><table class="agency-table reservations-table"><thead><tr><th>Référence</th><th>Client</th><th>Voiture</th><th>Date début</th><th>Date fin</th><th>Durée</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
        @forelse ($reservations as $reservation)
            <tr><td><strong>{{ $reservation->displayReference() }}</strong></td><td>{{ $reservation->client?->nom ?? '—' }}</td><td>{{ trim(($reservation->voiture?->marque ?? '').' '.($reservation->voiture?->modele ?? '')) ?: '—' }}</td><td>{{ $reservation->date_debut->format('d/m/Y') }}</td><td>{{ $reservation->date_fin->format('d/m/Y') }}</td><td>{{ $reservation->durationInDays() }} j</td><td><strong>{{ number_format((float) $reservation->montant, 0, ',', ' ') }} MAD</strong></td><td><x-status-badge type="reservation" :status="$reservation->statut" /></td><td><div class="vehicle-row-actions"><a href="{{ route('agence.reservations.show', $reservation) }}" aria-label="Voir la réservation"><x-icon name="eye" /></a><a href="{{ route('agence.reservations.edit', $reservation) }}" aria-label="Modifier la réservation"><x-icon name="edit" /></a>@if ($reservation->statut !== 'annulee')<form method="POST" action="{{ route('agence.reservations.annuler', $reservation) }}" data-confirm-delete="Annuler cette réservation ?">@csrf @method('PATCH')<button type="submit" aria-label="Annuler la réservation"><x-icon name="close" /></button></form>@endif</div></td></tr>
        @empty <tr><td colspan="9"><div class="agency-empty-state record-empty-state"><x-icon name="calendar" /><strong>Aucune réservation trouvée</strong><span>Créez votre première réservation ou modifiez vos filtres.</span><a class="agency-primary-button" href="{{ route('agence.reservations.create') }}"><x-icon name="plus" /> Créer une réservation</a></div></td></tr>@endforelse
        </tbody></table></div>
        @include('agence.partials.pagination', ['paginator' => $reservations])
    </section>
@endsection
