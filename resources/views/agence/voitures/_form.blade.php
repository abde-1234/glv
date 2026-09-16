@php($editing = $voiture->exists)

<div class="vehicle-form-fields">
    <div class="vehicle-form-grid">
        <label class="agency-form-field field-half">
            <span>Marque <b>*</b></span>
            <input type="text" name="marque" value="{{ old('marque', $voiture->marque) }}" placeholder="Ex: Peugeot" required maxlength="100">
            @error('marque')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-half">
            <span>Modèle <b>*</b></span>
            <input type="text" name="modele" value="{{ old('modele', $voiture->modele) }}" placeholder="Ex: 208" required maxlength="100">
            @error('modele')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-half">
            <span>Immatriculation <b>*</b></span>
            <input type="text" name="immatriculation" value="{{ old('immatriculation', $voiture->immatriculation) }}" placeholder="Ex: PE-123-AB" required maxlength="50">
            @error('immatriculation')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-half">
            <span>Catégorie</span>
            <input type="text" name="categorie" value="{{ old('categorie', $voiture->categorie) }}" placeholder="Ex: Citadine" maxlength="100">
            @error('categorie')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-third">
            <span>Année</span>
            <input type="number" name="annee" value="{{ old('annee', $voiture->annee) }}" placeholder="Ex: 2024" min="1950" max="{{ now()->year + 1 }}">
            @error('annee')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-third">
            <span>Prix par jour (MAD)</span>
            <input type="number" name="prix_jour" value="{{ old('prix_jour', $voiture->prix_jour) }}" placeholder="Ex: 300" min="0" step="0.01">
            @error('prix_jour')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-third">
            <span>Kilométrage</span>
            <input type="number" name="kilometrage" value="{{ old('kilometrage', $voiture->kilometrage) }}" placeholder="Ex: 45000" min="0">
            @error('kilometrage')<small>{{ $message }}</small>@enderror
        </label>
        <label class="agency-form-field field-half field-status">
            <span>Statut <b>*</b></span>
            <select name="statut" required>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('statut', $voiture->statut ?: 'disponible') === $value)>{{ $label }}</option>@endforeach</select>
            @error('statut')<small>{{ $message }}</small>@enderror
        </label>
    </div>

    <aside class="vehicle-photo-field">
        <h2>Photo du véhicule</h2>
        <label class="vehicle-photo-dropzone">
            <img src="{{ $voiture->photo ? asset('storage/'.$voiture->photo) : '' }}" alt="Aperçu de la voiture" data-photo-preview @if (! $voiture->photo) hidden @endif>
            <span data-photo-placeholder @if ($voiture->photo) hidden @endif><x-icon name="upload" /><strong>Cliquez pour ajouter une photo</strong><small>ou glissez une image ici</small><em>JPG, PNG, WEBP · max 2 Mo</em></span>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
        </label>
        @error('photo')<small class="vehicle-photo-error">{{ $message }}</small>@enderror
        @if ($editing && $voiture->photo)<p>Choisir une nouvelle image remplacera la photo actuelle.</p>@endif
    </aside>
</div>

<footer class="vehicle-form-footer">
    <a class="agency-secondary-button" href="{{ $editing ? route('agence.voitures.show', $voiture) : route('agence.voitures.index') }}">Annuler</a>
    <button class="agency-primary-button" type="submit"><x-icon name="check" /> {{ $editing ? 'Enregistrer les modifications' : 'Enregistrer la voiture' }}</button>
</footer>
