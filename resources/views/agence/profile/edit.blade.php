@extends('layouts.agence')
@section('title', 'Mon profil')
@section('content')
    @php($initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''))
    <header class="agency-page-heading settings-page-heading"><div><h1>Mon profil</h1><p>Gérez vos informations personnelles et votre mot de passe.</p></div></header>
    <div class="settings-profile-grid">
        <form class="agency-card settings-panel" method="POST" action="{{ route('agence.profile.update') }}">
            @csrf @method('PUT')
            <header class="settings-profile-heading"><span class="profile-avatar-large">{{ $initials ?: 'U' }}</span><div><h2>Informations personnelles</h2><p>{{ $user->email }}</p></div></header>
            <div class="settings-form-grid single-column">
                <label class="agency-form-field"><span>Nom complet <b>*</b></span><input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">@error('name')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Email <b>*</b></span><input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">@error('email')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Téléphone</span><input type="tel" name="telephone" value="{{ old('telephone', $user->telephone) }}" maxlength="30" autocomplete="tel">@error('telephone')<small>{{ $message }}</small>@enderror</label>
            </div>
            <footer class="settings-form-actions"><button class="agency-primary-button" type="submit"><x-icon name="check" width="18" height="18" /> Enregistrer</button></footer>
        </form>
        <form id="password" class="agency-card settings-panel" method="POST" action="{{ route('agence.profile.password') }}">
            @csrf @method('PUT')
            <header class="settings-profile-heading"><div><h2>Changer le mot de passe</h2><p>Choisissez un mot de passe d’au moins 8 caractères.</p></div></header>
            <div class="settings-form-grid single-column">
                <label class="agency-form-field"><span>Mot de passe actuel <b>*</b></span><input type="password" name="current_password" required autocomplete="current-password">@error('current_password')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Nouveau mot de passe <b>*</b></span><input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">@error('password')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Confirmation <b>*</b></span><input type="password" name="password_confirmation" required minlength="8" maxlength="72" autocomplete="new-password"></label>
            </div>
            <footer class="settings-form-actions"><button class="agency-primary-button" type="submit">Modifier le mot de passe</button></footer>
        </form>
    </div>
@endsection
