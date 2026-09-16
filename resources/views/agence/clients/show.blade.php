@extends('layouts.agence')
@section('title', $client->nom)
@section('content')
    <a class="agency-back-link" href="{{ route('agence.clients.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="record-detail-heading"><div><h1>{{ $client->nom }}</h1><x-status-badge type="client" :status="$client->statut" /></div><div><a class="agency-secondary-button" href="{{ route('agence.clients.edit', $client) }}"><x-icon name="edit" /> Modifier</a><a class="agency-primary-button" href="{{ route('agence.reservations.create', ['client_id' => $client->id]) }}"><x-icon name="plus" /> Nouvelle réservation</a></div></header>

    <section class="record-detail-grid client-detail-grid">
        <article class="agency-card record-info-card">
            <div class="record-section-heading"><h2>Informations du client</h2></div>
            <div class="client-profile"><span>{{ str($client->nom)->substr(0, 2)->upper() }}</span><div><strong>{{ $client->nom }}</strong><small>Client depuis le {{ $client->created_at->format('d/m/Y') }}</small></div></div>
            <dl class="record-definition-grid"><div><dt>Téléphone</dt><dd>{{ $client->telephone }}</dd></div><div><dt>CIN</dt><dd>{{ $client->cin ?: '—' }}</dd></div><div><dt>Email</dt><dd>{{ $client->email ?: '—' }}</dd></div><div><dt>Ville</dt><dd>{{ $client->ville ?: '—' }}</dd></div><div class="wide"><dt>Adresse</dt><dd>{{ $client->adresse ?: '—' }}</dd></div><div class="wide"><dt>Notes</dt><dd>{{ $client->notes ?: 'Aucune note pour ce client.' }}</dd></div></dl>
        </article>

        <article class="agency-card record-history-card">
            <div class="agency-card-heading"><h2>Historique des réservations</h2><span>{{ $recentReservations->count() }} récente{{ $recentReservations->count() > 1 ? 's' : '' }}</span></div>
            <div class="agency-table-scroll"><table class="agency-table"><thead><tr><th>Référence</th><th>Voiture</th><th>Période</th><th>Montant</th><th>Statut</th><th></th></tr></thead><tbody>
            @forelse ($recentReservations as $reservation)<tr><td><strong>{{ $reservation->displayReference() }}</strong></td><td>{{ $reservation->voiture?->marque }} {{ $reservation->voiture?->modele }}</td><td>{{ $reservation->date_debut->format('d/m/Y') }} – {{ $reservation->date_fin->format('d/m/Y') }}</td><td>{{ number_format((float) $reservation->montant, 0, ',', ' ') }} MAD</td><td><x-status-badge type="reservation" :status="$reservation->statut" /></td><td><a class="record-inline-link" href="{{ route('agence.reservations.show', $reservation) }}"><x-icon name="arrow-right" /></a></td></tr>
            @empty <tr><td colspan="6"><div class="agency-empty-state"><x-icon name="calendar" /><strong>Aucune réservation</strong><span>L’historique de ce client apparaîtra ici.</span></div></td></tr>@endforelse
            </tbody></table></div>
        </article>
    </section>
@endsection
