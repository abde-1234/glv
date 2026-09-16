@extends('layouts.agence')
@section('title', 'Paramètres de l’agence')
@section('content')
    <header class="agency-page-heading settings-page-heading">
        <div><h1>Paramètres de l’agence</h1><p>Gérez les informations principales de votre agence.</p></div>
        <button class="agency-primary-button" type="submit" form="agency-general-form"><x-icon name="check" width="18" height="18" /> Enregistrer</button>
    </header>
    <div class="agency-settings-layout">
        <x-agency-settings-nav />
        <div class="settings-stack">
            <section class="agency-card settings-overview" aria-label="Votre agence">
                <div class="settings-agency-avatar">
                    @if ($agence->logo)<img src="{{ asset('storage/'.$agence->logo) }}" width="64" height="64" alt="Logo {{ $agence->nom }}">@else<strong>GLV</strong>@endif
                </div>
                <div class="settings-overview-copy"><h2>{{ $agence->nom }}</h2><p>{{ $agence->description ?: 'Les informations de votre agence, réunies au même endroit.' }}</p><small>Créée le {{ $agence->created_at->format('d/m/Y') }}</small></div>
                <x-status-badge type="agency" :status="$agence->statut" />
            </section>
            <form id="agency-general-form" class="agency-card settings-panel" method="POST" action="{{ route('agence.settings.general.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <h2>Informations générales</h2>
                <div class="settings-form-grid">
                    <label class="agency-form-field full"><span>Nom de l’agence <b>*</b></span><input type="text" name="nom" value="{{ old('nom', $agence->nom) }}" required maxlength="255" autocomplete="organization">@error('nom')<small>{{ $message }}</small>@enderror</label>
                    <label class="agency-form-field full"><span>Description</span><textarea name="description" rows="3" maxlength="3000" placeholder="Présentez brièvement votre agence…">{{ old('description', $agence->description) }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>
                    <div class="agency-form-field full">
                        <span>Logo de l’agence</span>
                        <div class="agency-logo-editor">
                            <div class="agency-logo-preview">
                                @if ($agence->logo)<img src="{{ asset('storage/'.$agence->logo) }}" width="80" height="64" alt="Aperçu du logo" data-agency-logo-preview>
                                @else<span data-agency-logo-placeholder>GLV</span><img width="80" height="64" alt="Aperçu du logo" hidden data-agency-logo-preview>@endif
                            </div>
                            <label class="agency-logo-upload"><span>Changer le logo</span><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" data-agency-logo-input><small>PNG, JPG ou WEBP · 2 Mo maximum</small></label>
                        </div>
                        @error('logo')<small>{{ $message }}</small>@enderror
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
