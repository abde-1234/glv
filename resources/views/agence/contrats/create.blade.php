@extends('layouts.agence')
@section('title', 'Nouveau contrat')
@section('content')
    @php
        $currentReservationId = (int) old('reservation_id', $selectedReservationId ?? 0);
        $currentReservation = $reservations->firstWhere('id', $currentReservationId);
    @endphp
    <a class="agency-back-link" href="{{ route('agence.contrats.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Nouveau contrat</h1><p>Créez un contrat à partir d’une réservation.</p></div></header>
    <form class="agency-card record-form-card" method="POST" action="{{ route('agence.contrats.store') }}" data-contract-form>@csrf
        <div class="record-form-with-summary contract-form-layout">
            <div class="reservation-form-grid contract-form-grid">
                <label class="agency-form-field full"><span>Réservation <b>*</b></span><select name="reservation_id" required data-contract-reservation><option value="">Sélectionner une réservation</option>@foreach ($reservations as $reservation)<option value="{{ $reservation->id }}" data-reference="{{ $reservation->displayReference() }}" data-client="{{ $reservation->client?->nom }}" data-vehicle="{{ $reservation->voiture?->marque }} {{ $reservation->voiture?->modele }}" data-period="{{ $reservation->date_debut->format('d/m/Y') }} – {{ $reservation->date_fin->format('d/m/Y') }}" data-amount="{{ number_format((float) $reservation->montant, 2, '.', '') }}" @selected($currentReservationId === $reservation->id)>{{ $reservation->displayReference() }} · {{ $reservation->client?->nom }} · {{ $reservation->voiture?->marque }} {{ $reservation->voiture?->modele }}</option>@endforeach</select>@error('reservation_id')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Statut <b>*</b></span><select name="statut" required>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('statut', 'actif') === $value)>{{ $label }}</option>@endforeach</select>@error('statut')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field full"><span>Notes</span><textarea name="notes" rows="4" placeholder="Conditions ou informations complémentaires…">{{ old('notes') }}</textarea>@error('notes')<small>{{ $message }}</small>@enderror</label>
                @if ($reservations->isEmpty())<div class="inline-empty-note">Aucune réservation disponible. <a href="{{ route('agence.reservations.create') }}">Créer une réservation</a></div>@endif
            </div>
            <aside class="reservation-summary contract-summary"><h2>Résumé du contrat</h2><div><span class="blue"><x-icon name="calendar" /></span><p>Réservation<strong data-contract-reference>{{ $currentReservation?->displayReference() ?? '—' }}</strong></p></div><div><span class="cyan"><x-icon name="users" /></span><p>Client<strong data-contract-client>{{ $currentReservation?->client?->nom ?? '—' }}</strong></p></div><div><span class="blue"><x-icon name="car" /></span><p>Voiture<strong data-contract-vehicle>{{ $currentReservation ? $currentReservation->voiture?->marque.' '.$currentReservation->voiture?->modele : '—' }}</strong></p></div><div><span class="cyan"><x-icon name="clock" /></span><p>Période<strong data-contract-period>{{ $currentReservation ? $currentReservation->date_debut->format('d/m/Y').' – '.$currentReservation->date_fin->format('d/m/Y') : '—' }}</strong></p></div><div><span class="blue"><x-icon name="card" /></span><p>Montant<strong><b data-contract-amount>{{ number_format((float) ($currentReservation?->montant ?? 0), 2, '.', '') }}</b> MAD</strong></p></div></aside>
        </div>
        <footer class="vehicle-form-footer"><a class="agency-secondary-button" href="{{ route('agence.contrats.index') }}">Annuler</a><button class="agency-primary-button" type="submit" @disabled($reservations->isEmpty())><x-icon name="check" /> Enregistrer le contrat</button></footer>
    </form>
@endsection
