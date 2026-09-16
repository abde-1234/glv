@extends('layouts.agence')

@section('title', 'Gestion des voitures')

@section('content')
    <header class="agency-page-heading">
        <div><h1>Gestion des voitures</h1><p>Gérez facilement votre flotte de véhicules.</p></div>
        <a class="agency-primary-button" href="{{ route('agence.voitures.create') }}"><x-icon name="plus" /> Ajouter une voiture</a>
    </header>

    <section class="vehicle-kpi-grid" aria-label="Statistiques de la flotte">
        <article class="vehicle-kpi"><span class="blue"><x-icon name="car" /></span><div><p>Total voitures</p><strong>{{ $stats['total'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="green"><x-icon name="calendar" /></span><div><p>Disponibles</p><strong>{{ $stats['disponible'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="cyan"><x-icon name="calendar" /></span><div><p>Louées</p><strong>{{ $stats['loue'] }}</strong></div></article>
        <article class="vehicle-kpi"><span class="orange"><x-icon name="settings" /></span><div><p>Maintenance</p><strong>{{ $stats['maintenance'] }}</strong></div></article>
    </section>

    <section class="agency-card vehicle-list-card">
        <form class="vehicle-filter-bar" method="GET" action="{{ route('agence.voitures.index') }}">
            <label class="vehicle-filter-search">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher (marque, modèle, immatriculation…)" aria-label="Rechercher une voiture">
            </label>
            <label><span>Statut</span><select name="statut"><option value="">Tous</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Catégorie</span><select name="categorie"><option value="">Toutes</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected(request('categorie') === $category)>{{ $category }}</option>@endforeach</select></label>
            <button class="agency-filter-button" type="submit">Filtrer</button>
            <a class="agency-reset-button" href="{{ route('agence.voitures.index') }}">Réinitialiser</a>
        </form>

        <div class="agency-table-scroll">
            <table class="agency-table vehicles-table">
                <thead><tr><th>Photo</th><th>Marque / Modèle</th><th>Immatriculation</th><th>Catégorie</th><th>Prix / jour</th><th>Kilométrage</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($voitures as $voiture)
                    <tr>
                        <td><img class="vehicle-thumb" src="{{ $voiture->photo ? asset('storage/'.$voiture->photo) : asset('images/glv-login-hero.png') }}" alt="{{ $voiture->marque }} {{ $voiture->modele }}"></td>
                        <td><strong class="vehicle-name">{{ $voiture->marque }} {{ $voiture->modele }}</strong></td>
                        <td><span class="vehicle-plate">{{ $voiture->immatriculation }}</span></td>
                        <td>{{ $voiture->categorie ?: '—' }}</td>
                        <td><strong>{{ $voiture->prix_jour !== null ? number_format((float) $voiture->prix_jour, 0, ',', ' ').' MAD' : '—' }}</strong></td>
                        <td>{{ $voiture->kilometrage !== null ? number_format($voiture->kilometrage, 0, ',', ' ').' km' : '—' }}</td>
                        <td><x-vehicle-status :status="$voiture->statut" /></td>
                        <td>
                            <div class="vehicle-row-actions">
                                <a href="{{ route('agence.voitures.show', $voiture) }}" aria-label="Voir {{ $voiture->marque }} {{ $voiture->modele }}"><x-icon name="eye" /></a>
                                <a href="{{ route('agence.voitures.edit', $voiture) }}" aria-label="Modifier {{ $voiture->marque }} {{ $voiture->modele }}"><x-icon name="edit" /></a>
                                <form method="POST" action="{{ route('agence.voitures.destroy', $voiture) }}" data-confirm-delete="Supprimer définitivement cette voiture ?">
                                    @csrf @method('DELETE')
                                    <button type="submit" aria-label="Supprimer {{ $voiture->marque }} {{ $voiture->modele }}"><x-icon name="trash" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="agency-empty-state vehicle-empty-state"><x-icon name="car" /><strong>Aucune voiture trouvée</strong><span>Ajoutez votre première voiture ou modifiez vos filtres.</span><a class="agency-primary-button" href="{{ route('agence.voitures.create') }}"><x-icon name="plus" /> Ajouter une voiture</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <footer class="vehicle-pagination">
            <span>Affichage de {{ $voitures->firstItem() ?? 0 }} à {{ $voitures->lastItem() ?? 0 }} sur {{ $voitures->total() }} résultat{{ $voitures->total() > 1 ? 's' : '' }}</span>
            @if ($voitures->hasPages())
                <nav aria-label="Pagination des voitures">
                    @if ($voitures->onFirstPage())<span aria-disabled="true"><x-icon name="arrow-left" /></span>@else<a href="{{ $voitures->previousPageUrl() }}"><x-icon name="arrow-left" /></a>@endif
                    @foreach (range(max(1, $voitures->currentPage() - 1), min($voitures->lastPage(), $voitures->currentPage() + 1)) as $page)
                        <a href="{{ $voitures->url($page) }}" @class(['active' => $page === $voitures->currentPage()])>{{ $page }}</a>
                    @endforeach
                    @if ($voitures->hasMorePages())<a href="{{ $voitures->nextPageUrl() }}"><x-icon name="arrow-right" /></a>@else<span aria-disabled="true"><x-icon name="arrow-right" /></span>@endif
                </nav>
            @endif
        </footer>
    </section>
@endsection
