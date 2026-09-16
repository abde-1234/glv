@extends('layouts.agence')
@section('title', 'Préférences')
@section('content')
    <header class="agency-page-heading settings-page-heading"><div><h1>Préférences</h1><p>Personnalisez les réglages de votre agence.</p></div><button class="agency-primary-button" type="submit" form="agency-preferences-form"><x-icon name="check" width="18" height="18" /> Enregistrer</button></header>
    <div class="agency-settings-layout">
        <x-agency-settings-nav />
        <form id="agency-preferences-form" class="settings-stack" method="POST" action="{{ route('agence.settings.preferences.update') }}">
            @csrf @method('PUT')
            <section class="agency-card settings-panel">
                <h2>Général</h2>
                <div class="settings-form-grid">
                    @foreach ([['devise', 'Devise', ['MAD' => 'MAD (Dirham marocain)', 'EUR' => 'EUR (Euro)', 'USD' => 'USD (Dollar américain)']], ['format_date', 'Format de date', ['d/m/Y' => 'jj/mm/aaaa', 'Y-m-d' => 'aaaa-mm-jj', 'd-m-Y' => 'jj-mm-aaaa']], ['langue', 'Langue', ['fr' => 'Français', 'en' => 'English', 'ar' => 'العربية']], ['elements_par_page', 'Éléments par page', [10 => '10', 25 => '25', 50 => '50']]] as [$name, $label, $options])
                        <label class="agency-form-field"><span>{{ $label }} <b>*</b></span><select name="{{ $name }}" required>@foreach ($options as $value => $option)<option value="{{ $value }}" @selected((string) old($name, $agence->{$name}) === (string) $value)>{{ $option }}</option>@endforeach</select>@error($name)<small>{{ $message }}</small>@enderror</label>
                    @endforeach
                </div>
            </section>
            <section class="agency-card settings-panel">
                <h2>Notifications</h2>
                <p class="settings-help">Vos choix sont enregistrés. Les envois automatiques ne sont pas encore activés.</p>
                @foreach ([['notifications_email', 'Notifications par email', 'Les informations importantes de votre agence'], ['rappel_reservation', 'Rappels de réservation', 'Un rappel avant le début de chaque location'], ['rapport_mensuel', 'Rapport mensuel', 'Un résumé de l’activité de votre agence']] as [$name, $label, $help])
                    <label class="preference-switch"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" role="switch" @checked(old($name, $agence->{$name}))><span aria-hidden="true"></span><span class="preference-switch-copy"><strong>{{ $label }}</strong><small>{{ $help }}</small></span></label>
                    @error($name)<p class="settings-form-error">{{ $message }}</p>@enderror
                @endforeach
            </section>
        </form>
    </div>
@endsection
