@extends('layouts.agence')

@section('title', $voiture->marque.' '.$voiture->modele)

@section('content')
    <a class="agency-back-link" href="{{ route('agence.voitures.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>

    <article class="agency-card vehicle-detail-card">
        <div class="vehicle-gallery">
            <img class="vehicle-main-photo" src="{{ $voiture->photo ? asset('storage/'.$voiture->photo) : asset('images/glv-login-hero.png') }}" alt="{{ $voiture->marque }} {{ $voiture->modele }}">
            <div class="vehicle-gallery-strip" aria-label="Aperçus du véhicule">
                @foreach (['Vue principale', 'Vue rapprochée', 'Vue alternative'] as $index => $viewLabel)
                    <img @class(['active' => $index === 0, 'alternate' => $index > 0]) src="{{ $voiture->photo ? asset('storage/'.$voiture->photo) : asset('images/glv-login-hero.png') }}" alt="{{ $viewLabel }}">
                @endforeach
            </div>
            <div class="vehicle-description"><h2>Description</h2><p>{{ $voiture->marque }} {{ $voiture->modele }}, {{ mb_strtolower($voiture->categorie ?: 'véhicule de location') }} suivi par votre agence GLV.</p></div>
        </div>

        <div class="vehicle-detail-content">
            <header>
                <div><h1>{{ $voiture->marque }} {{ $voiture->modele }}</h1><x-vehicle-status :status="$voiture->statut" /></div>
                <strong>{{ $voiture->prix_jour !== null ? number_format((float) $voiture->prix_jour, 0, ',', ' ') : '—' }} <small>MAD / jour</small></strong>
            </header>

            <dl class="vehicle-spec-grid">
                <div><dt>Marque</dt><dd>{{ $voiture->marque }}</dd></div>
                <div><dt>Modèle</dt><dd>{{ $voiture->modele }}</dd></div>
                <div><dt>Immatriculation</dt><dd>{{ $voiture->immatriculation }}</dd></div>
                <div><dt>Catégorie</dt><dd>{{ $voiture->categorie ?: '—' }}</dd></div>
                <div><dt>Année</dt><dd>{{ $voiture->annee ?: '—' }}</dd></div>
                <div><dt>Kilométrage</dt><dd>{{ $voiture->kilometrage !== null ? number_format($voiture->kilometrage, 0, ',', ' ').' km' : '—' }}</dd></div>
                <div><dt>Statut</dt><dd><x-vehicle-status :status="$voiture->statut" /></dd></div>
                <div><dt>Agence</dt><dd>{{ auth()->user()->agence->nom }}</dd></div>
            </dl>

            <footer class="vehicle-detail-actions">
                <a class="agency-primary-button" href="{{ route('agence.voitures.edit', $voiture) }}"><x-icon name="edit" /> Modifier</a>
                <form method="POST" action="{{ route('agence.voitures.destroy', $voiture) }}" data-confirm-delete="Supprimer définitivement cette voiture ?">
                    @csrf @method('DELETE')
                    <button class="agency-danger-button" type="submit"><x-icon name="trash" /> Supprimer</button>
                </form>
            </footer>
        </div>
    </article>
@endsection
