@php($editing = $client->exists)

<div class="record-form-grid">
    <label class="agency-form-field"><span>Nom complet <b>*</b></span><input type="text" name="nom" value="{{ old('nom', $client->nom) }}" placeholder="Ex: Yassine El Amrani" required maxlength="150">@error('nom')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field"><span>Téléphone <b>*</b></span><input type="tel" name="telephone" value="{{ old('telephone', $client->telephone) }}" placeholder="Ex: 06 10 20 30 40" required maxlength="30">@error('telephone')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field"><span>CIN</span><input type="text" name="cin" value="{{ old('cin', $client->cin) }}" placeholder="Ex: AE123456" maxlength="50">@error('cin')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field"><span>Email</span><input type="email" name="email" value="{{ old('email', $client->email) }}" placeholder="Ex: client@email.com">@error('email')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field"><span>Ville</span><input type="text" name="ville" value="{{ old('ville', $client->ville) }}" placeholder="Ex: Fès" maxlength="100">@error('ville')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field"><span>Statut <b>*</b></span><select name="statut" required><option value="actif" @selected(old('statut', $client->statut ?: 'actif') === 'actif')>Actif</option><option value="inactif" @selected(old('statut', $client->statut) === 'inactif')>Inactif</option></select>@error('statut')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field full"><span>Adresse</span><input type="text" name="adresse" value="{{ old('adresse', $client->adresse) }}" placeholder="Adresse complète" maxlength="255">@error('adresse')<small>{{ $message }}</small>@enderror</label>
    <label class="agency-form-field full"><span>Notes</span><textarea name="notes" rows="3" placeholder="Informations complémentaires…">{{ old('notes', $client->notes) }}</textarea>@error('notes')<small>{{ $message }}</small>@enderror</label>
</div>

<footer class="vehicle-form-footer"><a class="agency-secondary-button" href="{{ $editing ? route('agence.clients.show', $client) : route('agence.clients.index') }}">Annuler</a><button class="agency-primary-button" type="submit"><x-icon name="check" /> {{ $editing ? 'Enregistrer les modifications' : 'Enregistrer le client' }}</button></footer>
