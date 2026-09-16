@php
    $sections = [
        ['agence.settings.general', 'agence.settings.general*', 'settings', 'Informations générales'],
        ['agence.settings.contact', 'agence.settings.contact*', 'home', 'Adresse et contact'],
        ['agence.settings.subscription', 'agence.settings.subscription', 'card', 'Abonnement'],
        ['agence.settings.preferences', 'agence.settings.preferences*', 'clock', 'Préférences'],
        ['agence.settings.documents.index', 'agence.settings.documents.*', 'upload', 'Documents'],
        ['agence.settings.users.index', 'agence.settings.users.*', 'users', 'Utilisateurs'],
    ];
@endphp
<nav class="agency-settings-nav" aria-label="Sections des paramètres">
    @foreach ($sections as [$route, $pattern, $icon, $label])
        <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)]) @if(request()->routeIs($pattern)) aria-current="page" @endif>
            <x-icon :name="$icon" width="18" height="18" /> {{ $label }}
        </a>
    @endforeach
</nav>
