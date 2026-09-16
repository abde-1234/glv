@php
    $editing = $reservation->exists;
    $currentClientId = (int) old('client_id', $reservation->client_id ?: ($selectedClientId ?? 0));
    $currentVehicleId = (int) old('voiture_id', $reservation->voiture_id);
    $currentVehicle = $voitures->firstWhere('id', $currentVehicleId);
    $initialPrice = (float) ($currentVehicle?->prix_jour ?? $reservation->prix_jour ?? 0);
    $initialDuration = $reservation->date_debut && $reservation->date_fin ? $reservation->durationInDays() : 0;
    $initialAmount = $initialPrice * $initialDuration;
@endphp

<div class="record-form-with-summary" data-reservation-calculator>
    <div class="reservation-form-grid">
        <label class="agency-form-field"><span>Client <b>*</b></span><select name="client_id" required data-reservation-client><option value="">Sélectionner un client</option>@foreach ($clients as $client)<option value="{{ $client->id }}" data-label="{{ $client->nom }}" @selected($currentClientId === $client->id)>{{ $client->nom }} · {{ $client->telephone }}</option>@endforeach</select>@error('client_id')<small>{{ $message }}</small>@enderror<a class="field-helper-link" href="{{ route('agence.clients.create') }}">+ Nouveau client</a></label>
        <label class="agency-form-field"><span>Voiture <b>*</b></span><select name="voiture_id" required data-reservation-vehicle><option value="">Sélectionner une voiture</option>@foreach ($voitures as $voiture)<option value="{{ $voiture->id }}" data-label="{{ $voiture->marque }} {{ $voiture->modele }}" data-price="{{ (float) $voiture->prix_jour }}" @selected($currentVehicleId === $voiture->id)>{{ $voiture->marque }} {{ $voiture->modele }} · {{ $voiture->immatriculation }}</option>@endforeach</select>@error('voiture_id')<small>{{ $message }}</small>@enderror</label>
        <label class="agency-form-field"><span>Date début <b>*</b></span><input type="date" name="date_debut" value="{{ old('date_debut', $reservation->date_debut?->format('Y-m-d')) }}" required data-reservation-start>@error('date_debut')<small>{{ $message }}</small>@enderror</label>
        <label class="agency-form-field"><span>Date fin <b>*</b></span><input type="date" name="date_fin" value="{{ old('date_fin', $reservation->date_fin?->format('Y-m-d')) }}" required data-reservation-end>@error('date_fin')<small>{{ $message }}</small>@enderror</label>
        <label class="agency-form-field"><span>Prix par jour</span><div class="input-suffix"><input type="number" value="{{ number_format($initialPrice, 2, '.', '') }}" readonly data-reservation-price><b>MAD</b></div></label>
        <label class="agency-form-field"><span>Durée</span><div class="input-suffix"><input type="number" value="{{ $initialDuration }}" readonly data-reservation-duration><b>jour(s)</b></div></label>
        <label class="agency-form-field"><span>Montant total</span><div class="input-suffix"><input type="number" value="{{ number_format($initialAmount, 2, '.', '') }}" readonly data-reservation-amount><b>MAD</b></div></label>
        <label class="agency-form-field"><span>Statut <b>*</b></span><select name="statut" required>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('statut', $reservation->statut ?: 'confirmee') === $value)>{{ $label }}</option>@endforeach</select>@error('statut')<small>{{ $message }}</small>@enderror</label>
        <label class="agency-form-field full"><span>Notes</span><textarea name="notes" rows="3" placeholder="Informations complémentaires…">{{ old('notes', $reservation->notes) }}</textarea>@error('notes')<small>{{ $message }}</small>@enderror</label>
    </div>

    <aside class="reservation-summary">
        <h2>Résumé de la réservation</h2>
        <div><span class="blue"><x-icon name="users" /></span><p>Client<strong data-summary-client>{{ $clients->firstWhere('id', $currentClientId)?->nom ?? '—' }}</strong></p></div>
        <div><span class="cyan"><x-icon name="car" /></span><p>Voiture<strong data-summary-vehicle>{{ $currentVehicle ? $currentVehicle->marque.' '.$currentVehicle->modele : '—' }}</strong></p></div>
        <div><span class="blue"><x-icon name="calendar" /></span><p>Période<strong data-summary-period>—</strong></p></div>
        <div><span class="cyan"><x-icon name="clock" /></span><p>Durée<strong><b data-summary-duration>{{ $initialDuration }}</b> jour(s)</strong></p></div>
        <div><span class="blue"><x-icon name="card" /></span><p>Montant total<strong><b data-summary-amount>{{ number_format($initialAmount, 2, '.', '') }}</b> MAD</strong></p></div>
    </aside>
</div>

<footer class="vehicle-form-footer"><a class="agency-secondary-button" href="{{ $editing ? route('agence.reservations.show', $reservation) : route('agence.reservations.index') }}">Annuler</a><button class="agency-primary-button" type="submit"><x-icon name="check" /> {{ $editing ? 'Enregistrer les modifications' : 'Enregistrer la réservation' }}</button></footer>
