@extends('layouts.super-admin')

@section('title', 'Paramètres')

@section('content')
    <div class="sa-settings-page">
        <div class="page-heading">
            <div><h1>Paramètres</h1><p>Gérez les préférences de votre plateforme.</p></div>
        </div>

        @if ($errors->any())
            <div class="sa-settings-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="sa-settings-grid">
            <section class="form-card sa-platform-card" aria-labelledby="platform-title">
                <div class="form-section-title"><span><x-icon name="building" /></span><div><h2 id="platform-title">Informations plateforme</h2><p>Identité et coordonnées de contact de GLV.</p></div></div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="platform">
                    <div class="form-grid two-columns">
                        <label class="form-field full"><span>Nom de la plateforme <b>*</b></span><input name="platform_name" value="{{ old('platform_name', $settings->platform_name) }}" maxlength="255" required>@error('platform_name')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Email support</span><input type="email" name="support_email" value="{{ old('support_email', $settings->support_email) }}" placeholder="support@glv.ma" maxlength="255">@error('support_email')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Téléphone support</span><input type="tel" name="support_phone" value="{{ old('support_phone', $settings->support_phone) }}" placeholder="06 12 34 56 78" maxlength="30">@error('support_phone')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field full"><span>Adresse / société</span><textarea name="company_address" rows="3" maxlength="1000" placeholder="Société, adresse complète…">{{ old('company_address', $settings->company_address) }}</textarea>@error('company_address')<small>{{ $message }}</small>@enderror</label>
                        <div class="form-field full">
                            <span>Logo de la plateforme</span>
                            <div class="sa-settings-logo-row">
                                <div class="sa-settings-logo">
                                    @if ($settings->logo)
                                        <img src="{{ Storage::disk('public')->url($settings->logo) }}" alt="Logo de {{ $settings->platform_name }}">
                                    @else
                                        <x-icon name="car" /><strong>GLV</strong>
                                    @endif
                                </div>
                                <label class="sa-settings-upload"><span>Choisir un logo</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG ou WebP · 2 Mo maximum</small></label>
                            </div>
                            @error('logo')<small>{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="sa-settings-actions"><button type="submit" class="primary-button"><x-icon name="check" /> Enregistrer</button></div>
                </form>
            </section>

            <section class="form-card" aria-labelledby="preferences-title">
                <div class="form-section-title"><span><x-icon name="settings" /></span><div><h2 id="preferences-title">Préférences générales</h2><p>Valeurs par défaut de la plateforme.</p></div></div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="preferences">
                    <div class="form-grid two-columns">
                        <label class="form-field"><span>Langue par défaut</span><select name="default_language" required>@foreach (['fr' => 'Français', 'en' => 'English', 'ar' => 'العربية'] as $value => $label)<option value="{{ $value }}" @selected(old('default_language', $settings->default_language) === $value)>{{ $label }}</option>@endforeach</select>@error('default_language')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Devise par défaut</span><select name="default_currency" required>@foreach (['MAD' => 'MAD — Dirham marocain', 'EUR' => 'EUR — Euro', 'USD' => 'USD — Dollar américain'] as $value => $label)<option value="{{ $value }}" @selected(old('default_currency', $settings->default_currency) === $value)>{{ $label }}</option>@endforeach</select>@error('default_currency')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Format de date</span><select name="date_format" required>@foreach (['d/m/Y' => 'JJ/MM/AAAA', 'Y-m-d' => 'AAAA-MM-JJ', 'm/d/Y' => 'MM/JJ/AAAA'] as $value => $label)<option value="{{ $value }}" @selected(old('date_format', $settings->date_format) === $value)>{{ $label }}</option>@endforeach</select>@error('date_format')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Éléments par page</span><select name="per_page" required>@foreach ([10, 25, 50] as $count)<option value="{{ $count }}" @selected((int) old('per_page', $settings->per_page) === $count)>{{ $count }}</option>@endforeach</select>@error('per_page')<small>{{ $message }}</small>@enderror</label>
                    </div>
                    <div class="sa-settings-actions"><button type="submit" class="primary-button"><x-icon name="check" /> Enregistrer</button></div>
                </form>
            </section>

            <section class="form-card" id="compte" aria-labelledby="account-title">
                <div class="form-section-title"><span><x-icon name="users" /></span><div><h2 id="account-title">Compte Super Admin</h2><p>Vos informations personnelles et votre mot de passe.</p></div></div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="account">
                    <div class="form-grid two-columns">
                        <label class="form-field"><span>Nom <b>*</b></span><input name="name" autocomplete="name" value="{{ old('name', $user->name) }}" maxlength="255" required>@error('name')<small>{{ $message }}</small>@enderror</label>
                        <label class="form-field"><span>Email <b>*</b></span><input type="email" name="email" autocomplete="email" value="{{ old('email', $user->email) }}" maxlength="255" required>@error('email')<small>{{ $message }}</small>@enderror</label>
                    </div>
                    <div class="sa-settings-actions"><button type="submit" class="primary-button"><x-icon name="check" /> Enregistrer</button></div>
                </form>

                <details class="sa-settings-password" @if (old('section') === 'password') open @endif>
                    <summary>Modifier le mot de passe <x-icon name="chevron-down" /></summary>
                    <form method="POST" action="{{ route('super-admin.settings.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="section" value="password">
                        <div class="form-grid two-columns">
                            <label class="form-field full"><span>Mot de passe actuel <b>*</b></span><input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small>{{ $message }}</small>@enderror</label>
                            <label class="form-field"><span>Nouveau mot de passe <b>*</b></span><input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>@error('password')<small>{{ $message }}</small>@enderror</label>
                            <label class="form-field"><span>Confirmation <b>*</b></span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="72" required></label>
                        </div>
                        <div class="sa-settings-actions"><button type="submit" class="primary-button">Modifier le mot de passe</button></div>
                    </form>
                </details>
            </section>
            @include('super-admin.parametres.management')
        </div>
    </div>
@endsection
