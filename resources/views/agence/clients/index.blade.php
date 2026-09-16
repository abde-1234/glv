@extends('layouts.agence')

@section('title', 'Gestion des clients')

@section('content')
    <header class="agency-page-heading">
        <div><h1>Gestion des clients</h1><p>Gérez votre base clients et consultez leur activité.</p></div>
        <a class="agency-primary-button" href="{{ route('agence.clients.create') }}"><x-icon name="plus" /> Ajouter un client</a>
    </header>

    <section class="vehicle-kpi-grid" aria-label="Statistiques clients">
        <article class="vehicle-kpi"><span class="blue"><x-icon name="users" /></span><div><p>Total clients</p><strong>{{ $stats['total'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="green"><x-icon name="plus" /></span><div><p>Nouveaux ce mois</p><strong>{{ $stats['new'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="cyan"><x-icon name="card" /></span><div><p>Clients actifs</p><strong>{{ $stats['active'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="orange"><x-icon name="users" /></span><div><p>Clients fidèles</p><strong>{{ $stats['loyal'] }}</strong></div></article>
    </section>

    <section class="agency-card record-list-card">
        <form class="agency-record-filters client-filters" method="GET" action="{{ route('agence.clients.index') }}">
            <label class="record-search"><x-icon name="search" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher (nom, téléphone, CIN, email…)" aria-label="Rechercher un client"></label>
            <label><span>Ville</span><select name="ville"><option value="">Toutes</option>@foreach ($villes as $ville)<option value="{{ $ville }}" @selected(request('ville') === $ville)>{{ $ville }}</option>@endforeach</select></label>
            <label><span>Statut</span><select name="statut"><option value="">Tous</option><option value="actif" @selected(request('statut') === 'actif')>Actif</option><option value="inactif" @selected(request('statut') === 'inactif')>Inactif</option></select></label>
            <button class="agency-filter-button" type="submit">Filtrer</button>
            <a class="agency-reset-button" href="{{ route('agence.clients.index') }}">Réinitialiser</a>
        </form>

        <div class="agency-table-scroll">
            <table class="agency-table clients-table">
                <thead><tr><th>Nom complet</th><th>Téléphone</th><th>CIN</th><th>Email</th><th>Ville</th><th>Réservations</th><th>Dernière réservation</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td><div class="record-person"><span>{{ str($client->nom)->substr(0, 2)->upper() }}</span><strong>{{ $client->nom }}</strong></div></td>
                        <td>{{ $client->telephone }}</td><td>{{ $client->cin ?: '—' }}</td><td>{{ $client->email ?: '—' }}</td><td>{{ $client->ville ?: '—' }}</td>
                        <td><strong>{{ $client->reservations_count }}</strong></td>
                        <td>{{ $client->reservations_max_date_debut ? \Carbon\Carbon::parse($client->reservations_max_date_debut)->format('d/m/Y') : '—' }}</td>
                        <td><x-status-badge type="client" :status="$client->statut" /></td>
                        <td><div class="vehicle-row-actions"><a href="{{ route('agence.clients.show', $client) }}" aria-label="Voir {{ $client->nom }}"><x-icon name="eye" /></a><a href="{{ route('agence.clients.edit', $client) }}" aria-label="Modifier {{ $client->nom }}"><x-icon name="edit" /></a><form method="POST" action="{{ route('agence.clients.destroy', $client) }}" data-confirm-delete="Supprimer ce client et son historique associé ?">@csrf @method('DELETE')<button type="submit" aria-label="Supprimer {{ $client->nom }}"><x-icon name="trash" /></button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="agency-empty-state record-empty-state"><x-icon name="users" /><strong>Aucun client trouvé</strong><span>Ajoutez votre premier client ou modifiez vos filtres.</span><a class="agency-primary-button" href="{{ route('agence.clients.create') }}"><x-icon name="plus" /> Ajouter un client</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('agence.partials.pagination', ['paginator' => $clients])
    </section>
@endsection
