@extends('layouts.super-admin')

@section('title', $agence->nom)

@section('content')
    <div class="page-heading">
        <div><p class="page-eyebrow">Fiche agence</p><h1>{{ $agence->nom }}</h1><p>{{ $agence->ville ?: 'Ville non renseignée' }} · créée le {{ $agence->created_at->format('d/m/Y') }}</p></div>
        <div class="heading-actions"><a class="secondary-button" href="{{ route('super-admin.agences.index') }}"><x-icon name="arrow-left" /> Liste</a><a class="primary-button" href="{{ route('super-admin.agences.edit', $agence) }}"><x-icon name="edit" /> Modifier</a></div>
    </div>

    <section class="agency-detail-grid">
        <article class="content-card agency-profile-card">
            <div class="agency-profile-head">
                @if ($agence->logo)<img src="{{ Storage::url($agence->logo) }}" alt="Logo de {{ $agence->nom }}">@else<span>{{ str($agence->nom)->substr(0, 2)->upper() }}</span>@endif
                <div><h2>{{ $agence->nom }}</h2><p>{{ $agence->email }}</p><span class="status-badge {{ $agence->statut }}"><i></i>{{ ['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'][$agence->statut] }}</span></div>
            </div>
            <dl class="detail-list">
                <div><dt>Téléphone</dt><dd>{{ $agence->telephone ?: '—' }}</dd></div>
                <div><dt>Ville</dt><dd>{{ $agence->ville ?: '—' }}</dd></div>
                <div><dt>Adresse</dt><dd>{{ $agence->adresse ?: '—' }}</dd></div>
                <div><dt>Abonnement</dt><dd>{{ $agence->type_abonnement ?: 'Non défini' }}</dd></div>
                <div><dt>Expiration</dt><dd>{{ $agence->date_expiration?->format('d/m/Y') ?? 'Sans échéance' }}</dd></div>
            </dl>
        </article>

        <div class="detail-side-stack">
            <article class="content-card manager-card">
                <div class="card-heading"><div><span class="section-icon"><x-icon name="users" /></span><h2>Gérant principal</h2></div></div>
                @if ($agence->primaryAdmin)
                    <dl class="detail-list"><div><dt>Nom</dt><dd>{{ $agence->primaryAdmin->name }}</dd></div><div><dt>Email</dt><dd>{{ $agence->primaryAdmin->email }}</dd></div><div><dt>Téléphone</dt><dd>{{ $agence->primaryAdmin->telephone ?: '—' }}</dd></div></dl>
                @else
                    <div class="empty-state">Aucun gérant associé.</div>
                @endif
            </article>
            <article class="content-card detail-actions-card">
                <div class="card-heading"><div><span class="section-icon"><x-icon name="settings" /></span><h2>Accès agence</h2></div></div>
                @if ($agence->statut === 'suspendu')
                    <form method="POST" action="{{ route('super-admin.agences.activer', $agence) }}">@csrf @method('PATCH')<button class="primary-button" type="submit"><x-icon name="check" /> Activer l’agence</button></form>
                @else
                    <form method="POST" action="{{ route('super-admin.agences.suspendre', $agence) }}">@csrf @method('PATCH')<button class="danger-button" type="submit"><x-icon name="pause" /> Suspendre l’agence</button></form>
                @endif
            </article>
        </div>
    </section>

    <section class="kpi-grid detail-kpis">
        <article class="kpi-card"><span class="kpi-icon blue"><x-icon name="car" /></span><div><p>Voitures</p><strong>{{ $agence->voitures->count() }}</strong></div></article>
        <article class="kpi-card"><span class="kpi-icon indigo"><x-icon name="users" /></span><div><p>Clients</p><strong>{{ $agence->clients->count() }}</strong></div></article>
        <article class="kpi-card"><span class="kpi-icon cyan"><x-icon name="calendar" /></span><div><p>Réservations</p><strong>{{ $agence->reservations->count() }}</strong></div></article>
    </section>
@endsection
