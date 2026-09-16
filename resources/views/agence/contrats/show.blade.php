@extends('layouts.agence')
@section('title', $contrat->displayReference())
@section('content')
    <a class="agency-back-link" href="{{ route('agence.contrats.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="record-detail-heading contract-detail-heading">
        <div><h1>Contrat {{ $contrat->displayReference() }}</h1><x-status-badge type="contrat" :status="$contrat->statut" /></div>
        <div class="contract-heading-actions">
            <a class="agency-secondary-button" href="{{ route('agence.contrats.edit', $contrat) }}"><x-icon name="edit" /> Modifier</a>
            <a class="agency-secondary-button" href="{{ route('agence.contrats.pdf.preview', $contrat) }}" target="_blank" rel="noopener"><x-icon name="eye" /> Aperçu PDF</a>
            <a class="agency-primary-button" href="{{ route('agence.contrats.pdf.download', $contrat) }}"><x-icon name="download" /> Télécharger PDF</a>
            @if ($contrat->statut !== 'annule')
                <form method="POST" action="{{ route('agence.contrats.resilier', $contrat) }}" data-confirm-delete="Résilier ce contrat ?">@csrf @method('PATCH')<button class="agency-danger-button" type="submit">Résilier</button></form>
            @endif
        </div>
    </header>
    <article class="agency-card entity-detail-card contract-detail-card">
        <section class="detail-overview"><div><small>Référence</small><strong>{{ $contrat->displayReference() }}</strong></div><div><small>Statut</small><x-status-badge type="contrat" :status="$contrat->statut" /></div><div><small>Date de création</small><strong>{{ $contrat->created_at->format('d/m/Y à H:i') }}</strong></div></section>
        <div class="entity-detail-grid">
            <section class="detail-block"><h2>Client</h2><div class="detail-identity"><span>{{ str($contrat->client?->nom)->substr(0, 2)->upper() }}</span><p><strong>{{ $contrat->client?->nom ?? '—' }}</strong><small>{{ $contrat->client?->telephone ?: '—' }}</small><small>{{ $contrat->client?->email ?: '—' }}</small><small>{{ $contrat->client?->cin ?: '—' }}</small></p></div></section>
            <section class="detail-block"><h2>Voiture</h2><div class="detail-vehicle"><img src="{{ $contrat->voiture?->photo ? asset('storage/'.$contrat->voiture->photo) : asset('images/glv-login-hero.png') }}" alt="Voiture"><p><strong>{{ $contrat->voiture?->marque }} {{ $contrat->voiture?->modele }}</strong><small>{{ $contrat->voiture?->immatriculation }}</small><small>{{ $contrat->voiture?->categorie ?: '—' }}</small></p></div></section>
            <section class="detail-block detail-period"><h2>Période de location</h2><div><span><x-icon name="calendar" /></span><p><small>Date début</small><strong>{{ $contrat->date_debut->format('d/m/Y') }}</strong></p><p><small>Date fin</small><strong>{{ $contrat->date_fin->format('d/m/Y') }}</strong></p><p><small>Durée</small><strong>{{ $contrat->durationInDays() }} jour(s)</strong></p></div></section>
            <section class="detail-block detail-price"><h2>Montant</h2><div><span><x-icon name="card" /></span><p><small>Montant total</small><strong>{{ number_format((float) $contrat->montant, 0, ',', ' ') }} MAD</strong></p></div></section>
            @if ($contrat->reservation)<section class="detail-block linked-reservation"><h2>Réservation liée</h2><div><span><x-icon name="calendar" /></span><p><small>Référence</small><strong>{{ $contrat->reservation->displayReference() }}</strong></p><x-status-badge type="reservation" :status="$contrat->reservation->statut" /><a class="agency-secondary-button" href="{{ route('agence.reservations.show', $contrat->reservation) }}">Voir la réservation</a></div></section>@endif
            <section class="detail-block detail-notes"><h2>Notes</h2><p>{{ $contrat->notes ?: 'Aucune note pour ce contrat.' }}</p></section>
            <section class="detail-block contract-documents">
                <div class="contract-document-icon"><x-icon name="document" /></div>
                <div class="contract-document-copy">
                    <h2>Document du contrat</h2>
                    <p>Version PDF générée à partir des informations actuelles du contrat.</p>
                    <small>GLV-contrat-{{ $contrat->displayReference() }}.pdf</small>
                </div>
                <div class="contract-document-actions">
                    <a class="agency-secondary-button" href="{{ route('agence.contrats.pdf.preview', $contrat) }}" target="_blank" rel="noopener"><x-icon name="eye" /> Aperçu</a>
                    <a class="agency-primary-button" href="{{ route('agence.contrats.pdf.download', $contrat) }}"><x-icon name="download" /> Télécharger</a>
                </div>
            </section>
        </div>
    </article>
@endsection
