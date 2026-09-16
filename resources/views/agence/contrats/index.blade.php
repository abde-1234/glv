@extends('layouts.agence')
@section('title', 'Contrats de location')
@section('content')
    <header class="agency-page-heading"><div><h1>Contrats de location</h1><p>Suivez et gérez les contrats de votre agence.</p></div><a class="agency-primary-button" href="{{ route('agence.contrats.create') }}"><x-icon name="plus" /> Nouveau contrat</a></header>
    <section class="vehicle-kpi-grid" aria-label="Statistiques des contrats">
        <article class="vehicle-kpi"><span class="blue"><x-icon name="card" /></span><div><p>Total contrats</p><strong>{{ $stats['total'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="green"><x-icon name="check" /></span><div><p>Actifs</p><strong>{{ $stats['actif'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="cyan"><x-icon name="calendar" /></span><div><p>Terminés</p><strong>{{ $stats['termine'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="orange"><x-icon name="close" /></span><div><p>Annulés</p><strong>{{ $stats['annule'] }}</strong></div></article>
    </section>
    <section class="agency-card record-list-card">
        <form class="agency-record-filters contract-filters" method="GET" action="{{ route('agence.contrats.index') }}">
            <label class="record-search"><x-icon name="search" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher référence, client, voiture…" aria-label="Rechercher un contrat"></label>
            <label><span>Statut</span><select name="statut"><option value="">Tous</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Date début</span><input type="date" name="date_debut" value="{{ request('date_debut') }}"></label><label><span>Date fin</span><input type="date" name="date_fin" value="{{ request('date_fin') }}"></label>
            <button class="agency-filter-button" type="submit">Filtrer</button><a class="agency-reset-button" href="{{ route('agence.contrats.index') }}">Réinitialiser</a>
        </form>
        <div class="agency-table-scroll"><table class="agency-table contracts-table"><thead><tr><th>Référence</th><th>Client</th><th>Voiture</th><th>Date début</th><th>Date fin</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
        @forelse ($contrats as $contrat)<tr><td><strong>{{ $contrat->displayReference() }}</strong></td><td>{{ $contrat->client?->nom ?? '—' }}</td><td>{{ trim(($contrat->voiture?->marque ?? '').' '.($contrat->voiture?->modele ?? '')) ?: '—' }}</td><td>{{ $contrat->date_debut->format('d/m/Y') }}</td><td>{{ $contrat->date_fin->format('d/m/Y') }}</td><td><strong>{{ number_format((float) $contrat->montant, 0, ',', ' ') }} MAD</strong></td><td><x-status-badge type="contrat" :status="$contrat->statut" /></td><td><div class="vehicle-row-actions"><a href="{{ route('agence.contrats.show', $contrat) }}" aria-label="Voir le contrat"><x-icon name="eye" /></a><a class="contract-download-action" href="{{ route('agence.contrats.pdf.download', $contrat) }}" aria-label="Télécharger le contrat PDF" title="Télécharger le PDF"><x-icon name="download" /></a><a href="{{ route('agence.contrats.edit', $contrat) }}" aria-label="Modifier le contrat"><x-icon name="edit" /></a>@if ($contrat->statut !== 'annule')<form method="POST" action="{{ route('agence.contrats.resilier', $contrat) }}" data-confirm-delete="Résilier ce contrat ?">@csrf @method('PATCH')<button type="submit" aria-label="Résilier le contrat"><x-icon name="close" /></button></form>@endif</div></td></tr>
        @empty <tr><td colspan="8"><div class="agency-empty-state record-empty-state"><x-icon name="card" /><strong>Aucun contrat trouvé</strong><span>Créez un contrat depuis une réservation existante.</span><a class="agency-primary-button" href="{{ route('agence.contrats.create') }}"><x-icon name="plus" /> Créer un contrat</a></div></td></tr>@endforelse
        </tbody></table></div>
        @include('agence.partials.pagination', ['paginator' => $contrats])
    </section>
@endsection
