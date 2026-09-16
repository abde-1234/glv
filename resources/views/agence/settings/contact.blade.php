@extends('layouts.agence')
@section('title', 'Adresse et contact')
@section('content')
    <form method="POST" action="{{ route('agence.settings.contact.update') }}">@csrf @method('PUT')
        <header class="agency-page-heading settings-page-heading"><div><h1>Adresse et contact</h1><p>Mettez à jour les informations de contact de votre agence.</p></div><button class="agency-primary-button" type="submit"><x-icon name="check" /> Enregistrer</button></header>
        <div class="agency-settings-layout"><x-agency-settings-nav />
            <section class="agency-card settings-panel"><h2>Adresse</h2><div class="settings-form-grid">
                <label class="agency-form-field full"><span>Adresse complète <b>*</b></span><input type="text" name="adresse" value="{{ old('adresse', $agence->adresse) }}" required maxlength="1000">@error('adresse')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Ville <b>*</b></span><input type="text" name="ville" value="{{ old('ville', $agence->ville) }}" required maxlength="100">@error('ville')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Code postal</span><input type="text" name="code_postal" value="{{ old('code_postal', $agence->code_postal) }}" maxlength="20">@error('code_postal')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Pays <b>*</b></span><input type="text" name="pays" value="{{ old('pays', $agence->pays ?: 'Maroc') }}" required maxlength="100">@error('pays')<small>{{ $message }}</small>@enderror</label>
                <h2 class="settings-subheading full">Contacts</h2>
                <label class="agency-form-field"><span>Téléphone <b>*</b></span><input type="tel" name="telephone" value="{{ old('telephone', $agence->telephone) }}" required maxlength="30">@error('telephone')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field"><span>Email <b>*</b></span><input type="email" name="email" value="{{ old('email', $agence->email) }}" required maxlength="255">@error('email')<small>{{ $message }}</small>@enderror</label>
                <label class="agency-form-field full"><span>Site web</span><input type="url" name="site_web" value="{{ old('site_web', $agence->site_web) }}" placeholder="https://www.mon-agence.ma" maxlength="255">@error('site_web')<small>{{ $message }}</small>@enderror</label>
            </div></section>
        </div>
    </form>
@endsection
