@extends('layouts.super-admin')

@section('title', 'Gestion des agences')

@section('content')
    <div class="page-heading">
        <div>
            <p class="page-eyebrow">Réseau GLV</p>
            <h1>Gestion des agences</h1>
            <p>Ajoutez, suivez et contrôlez les agences inscrites sur votre plateforme.</p>
        </div>
        <a class="primary-button" href="{{ route('super-admin.agences.create') }}"><x-icon name="plus" /> Ajouter une agence</a>
    </div>

    <section class="kpi-grid agency-kpis" aria-label="Statistiques des agences">
        <article class="kpi-card"><span class="kpi-icon blue"><x-icon name="building" /></span><div><p>Agences totales</p><strong>{{ $totalAgencies }}</strong><small>réseau complet</small></div></article>
        <article class="kpi-card"><span class="kpi-icon green"><x-icon name="check" /></span><div><p>Agences actives</p><strong>{{ $activeAgencies }}</strong><small>opérationnelles</small></div></article>
        <article class="kpi-card"><span class="kpi-icon orange"><x-icon name="clock" /></span><div><p>En période d’essai</p><strong>{{ $trialAgencies }}</strong><small>à accompagner</small></div></article>
        <article class="kpi-card"><span class="kpi-icon red"><x-icon name="pause" /></span><div><p>Agences suspendues</p><strong>{{ $suspendedAgencies }}</strong><small>accès bloqué</small></div></article>
    </section>

    <section class="content-card list-card">
        <form class="filter-bar" method="GET" action="{{ route('super-admin.agences.index') }}">
            <label class="filter-search">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Agence, gérant, téléphone, email…" aria-label="Rechercher">
            </label>
            <label>
                <span class="sr-only">Statut</span>
                <select name="statut">
                    <option value="">Tous les statuts</option>
                    @foreach (['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Ville</span>
                <select name="ville">
                    <option value="">Toutes les villes</option>
                    @foreach ($cities as $ville)<option value="{{ $ville }}" @selected(request('ville') === $ville)>{{ $ville }}</option>@endforeach
                </select>
            </label>
            <button class="filter-button" type="submit">Filtrer</button>
            @if (request()->hasAny(['q', 'statut', 'ville']))
                <a class="reset-filter" href="{{ route('super-admin.agences.index') }}">Réinitialiser</a>
            @endif
        </form>

        <div class="table-scroll">
            <table class="data-table agencies-table">
                <thead>
                    <tr><th>Agence</th><th>Gérant</th><th>Téléphone</th><th>Ville</th><th>Abonnement</th><th>Statut</th><th>Création</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($agences as $agence)
                        <tr>
                            <td><div class="agency-cell"><span class="agency-avatar">{{ str($agence->nom)->substr(0, 2)->upper() }}</span><span><strong>{{ $agence->nom }}</strong><small>{{ $agence->email }}</small></span></div></td>
                            <td>{{ $agence->primaryAdmin?->name ?? '—' }}</td>
                            <td>{{ $agence->telephone ?: '—' }}</td>
                            <td>{{ $agence->ville ?: '—' }}</td>
                            <td><span class="plan-badge">{{ $agence->type_abonnement ?: 'Non défini' }}</span></td>
                            <td><span class="status-badge {{ $agence->statut }}"><i></i>{{ ['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'][$agence->statut] }}</span></td>
                            <td>{{ $agence->created_at->format('d/m/Y') }}</td>
                            <td>
                                <details class="table-actions">
                                    <summary aria-label="Actions pour {{ $agence->nom }}"><x-icon name="dots" /></summary>
                                    <div>
                                        <a href="{{ route('super-admin.agences.show', $agence) }}"><x-icon name="eye" /> Voir</a>
                                        <a href="{{ route('super-admin.agences.edit', $agence) }}"><x-icon name="edit" /> Modifier</a>
                                        @if ($agence->statut === 'suspendu')
                                            <form method="POST" action="{{ route('super-admin.agences.activer', $agence) }}">@csrf @method('PATCH')<button type="submit"><x-icon name="check" /> Activer</button></form>
                                        @else
                                            <form method="POST" action="{{ route('super-admin.agences.suspendre', $agence) }}">@csrf @method('PATCH')<button type="submit"><x-icon name="pause" /> Suspendre</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('super-admin.agences.destroy', $agence) }}" onsubmit="return confirm('Supprimer définitivement cette agence et ses données ?')">
                                            @csrf @method('DELETE')
                                            <button class="danger" type="submit"><x-icon name="trash" /> Supprimer</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>Aucune agence trouvée</strong><span>Modifiez vos filtres ou ajoutez une nouvelle agence.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($agences->hasPages())
            <div class="pagination-wrap">{{ $agences->links() }}</div>
        @endif
    </section>
@endsection
