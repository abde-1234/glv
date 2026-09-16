@php
    $editing = isset($agence);
    $gerant = $editing ? $agence->primaryAdmin : null;
    $formAction = $editing ? route('super-admin.agences.update', $agence) : route('super-admin.agences.store');
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="agency-form">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="agency-form-main">
        <section class="form-card">
            <div class="form-section-title"><span><x-icon name="building" /></span><div><h2>Informations générales</h2><p>Coordonnées publiques et identité de l’agence.</p></div></div>
            <div class="form-grid two-columns">
                <label class="form-field"><span>Nom de l’agence <b>*</b></span><input type="text" name="nom" value="{{ old('nom', $agence->nom ?? '') }}" placeholder="Ex. Atlas Rent" required>@error('nom')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Email <b>*</b></span><input type="email" name="email" value="{{ old('email', $agence->email ?? '') }}" placeholder="contact@agence.ma" required>@error('email')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Téléphone <b>*</b></span><input type="text" name="telephone" value="{{ old('telephone', $agence->telephone ?? '') }}" placeholder="06 12 34 56 78" required>@error('telephone')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Ville <b>*</b></span><input type="text" name="ville" value="{{ old('ville', $agence->ville ?? '') }}" placeholder="Casablanca" required>@error('ville')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field full"><span>Adresse complète</span><input type="text" name="adresse" value="{{ old('adresse', $agence->adresse ?? '') }}" placeholder="Avenue, numéro, quartier…">@error('adresse')<small>{{ $message }}</small>@enderror</label>
            </div>
        </section>

        <section class="form-card">
            <div class="form-section-title"><span><x-icon name="users" /></span><div><h2>Gérant principal</h2><p>Identifiants utilisés pour accéder à l’espace agence.</p></div></div>
            <div class="form-grid two-columns">
                <label class="form-field"><span>Nom du gérant <b>*</b></span><input type="text" name="gerant_name" value="{{ old('gerant_name', $gerant?->name) }}" placeholder="Ahmed Benali" required>@error('gerant_name')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Téléphone du gérant <b>*</b></span><input type="text" name="gerant_telephone" value="{{ old('gerant_telephone', $gerant?->telephone) }}" placeholder="06 12 34 56 78" required>@error('gerant_telephone')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Email du gérant <b>*</b></span><input type="email" name="gerant_email" value="{{ old('gerant_email', $gerant?->email) }}" placeholder="gerant@agence.ma" required>@error('gerant_email')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>{{ $editing ? 'Nouveau mot de passe' : 'Mot de passe temporaire' }} @unless($editing)<b>*</b>@endunless</span><input type="password" name="gerant_password" placeholder="{{ $editing ? 'Laisser vide pour conserver' : '8 caractères minimum' }}" @required(!$editing)>@error('gerant_password')<small>{{ $message }}</small>@enderror</label>
            </div>
        </section>
    </div>

    <aside class="agency-form-aside">
        <section class="form-card logo-card">
            <div class="form-section-title compact"><span><x-icon name="upload" /></span><div><h2>Logo de l’agence</h2></div></div>
            <label class="logo-dropzone">
                @if ($editing && $agence->logo)
                    <img src="{{ Storage::url($agence->logo) }}" alt="Logo actuel de {{ $agence->nom }}">
                @else
                    <x-icon name="upload" />
                @endif
                <strong>{{ $editing && $agence->logo ? 'Remplacer le logo' : 'Cliquez pour ajouter un logo' }}</strong>
                <small>PNG, JPG ou WebP — 2 Mo maximum</small>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            </label>
            @error('logo')<p class="upload-error">{{ $message }}</p>@enderror
        </section>

        <section class="form-card">
            <div class="form-section-title compact"><span><x-icon name="settings" /></span><div><h2>Statut et abonnement</h2></div></div>
            <div class="form-grid">
                <label class="form-field"><span>Statut <b>*</b></span><select name="statut" required>@foreach (['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'] as $value => $label)<option value="{{ $value }}" @selected(old('statut', $agence->statut ?? 'essai') === $value)>{{ $label }}</option>@endforeach</select>@error('statut')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Type d’abonnement</span><select name="type_abonnement"><option value="">Non défini</option>@foreach (['Essai', 'Basic', 'Pro', 'Premium'] as $plan)<option value="{{ $plan }}" @selected(old('type_abonnement', $agence->type_abonnement ?? '') === $plan)>{{ $plan }}</option>@endforeach</select>@error('type_abonnement')<small>{{ $message }}</small>@enderror</label>
                <label class="form-field"><span>Date d’expiration</span><input type="date" name="date_expiration" value="{{ old('date_expiration', isset($agence) && $agence->date_expiration ? $agence->date_expiration->format('Y-m-d') : '') }}">@error('date_expiration')<small>{{ $message }}</small>@enderror</label>
            </div>
            <div class="form-info"><x-icon name="info" /><span>L’agence utilisera les identifiants du gérant pour accéder à son espace.</span></div>
            <button class="primary-button form-submit" type="submit"><x-icon name="check" /> {{ $editing ? 'Enregistrer les modifications' : 'Enregistrer l’agence' }}</button>
            <a class="secondary-button form-cancel" href="{{ $editing ? route('super-admin.agences.show', $agence) : route('super-admin.agences.index') }}"><x-icon name="close" /> Annuler</a>
        </section>
    </aside>
</form>
