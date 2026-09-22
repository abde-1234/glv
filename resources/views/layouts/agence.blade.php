<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Espace de gestion de votre agence GLV.">
    <title>@yield('title', 'Espace Agence') — GLV</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/glv-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="agency-body">
    @php
        $agencyUser = auth()->user();
        $agency = $agencyUser->agence;
        $agencyInitials = collect(preg_split('/\s+/', trim($agencyUser->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $agencySearchRoute = match (true) {
            request()->routeIs('agence.clients.*') => route('agence.clients.index'),
            request()->routeIs('agence.reservations.*') => route('agence.reservations.index'),
            request()->routeIs('agence.contrats.*') => route('agence.contrats.index'),
            default => route('agence.voitures.index'),
        };
        $agencySearchPlaceholder = match (true) {
            request()->routeIs('agence.clients.*') => 'Rechercher un client…',
            request()->routeIs('agence.reservations.*') => 'Rechercher une réservation…',
            request()->routeIs('agence.contrats.*') => 'Rechercher un contrat…',
            default => 'Rechercher une voiture…',
        };
    @endphp

    <div class="agency-shell">
        <aside class="agency-sidebar" id="agency-sidebar" data-admin-sidebar aria-label="Navigation de l’agence">
            <a class="agency-brand" href="{{ route('dashboard') }}">
                <picture><source media="(min-width: 901px) and (max-width: 1180px)" srcset="{{ asset('images/branding/glv-mark.png') }}"><img class="agency-brand-image" src="{{ asset('images/branding/glv-logo.png') }}" width="2172" height="724" alt="GLV — Location de voitures"></picture>
            </a>

            <nav class="agency-nav" aria-label="Menu principal">
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
                    <x-icon name="home" /> <span>Tableau de bord</span>
                </a>
                <a href="{{ route('agence.voitures.index') }}" @class(['active' => request()->routeIs('agence.voitures.*')])>
                    <x-icon name="car" /> <span>Voitures</span>
                </a>
                <a href="{{ route('agence.clients.index') }}" @class(['active' => request()->routeIs('agence.clients.*')])><x-icon name="users" /> <span>Clients</span></a>
                <a href="{{ route('agence.reservations.index') }}" @class(['active' => request()->routeIs('agence.reservations.*')])><x-icon name="calendar" /> <span>Réservations</span></a>
                <a href="{{ route('agence.contrats.index') }}" @class(['active' => request()->routeIs('agence.contrats.*')])><x-icon name="card" /> <span>Contrats</span></a>
                <a href="{{ $agencyUser->role === \App\Models\User::ROLE_ADMIN_AGENCE ? route('agence.settings.general') : route('agence.profile.edit') }}" @class(['active' => request()->routeIs('agence.settings.*', 'agence.profile.*')])><x-icon name="settings" /> <span>Profil / Paramètres</span></a>
            </nav>

            <div class="agency-sidebar-spacer"></div>

            <div class="agency-sidebar-visual" aria-hidden="true">
                <img src="{{ asset('images/glv-login-hero.png') }}" alt="">
                <p>Votre agence,<br>plus loin avec GLV.</p>
            </div>

            <nav class="agency-nav agency-nav-bottom">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><x-icon name="logout" /> <span>Déconnexion</span></button>
                </form>
            </nav>
        </aside>

        <button class="agency-sidebar-backdrop" type="button" data-admin-sidebar-close aria-label="Fermer le menu"></button>

        <div class="agency-main">
            <header class="agency-topbar">
                <button class="agency-topbar-menu" type="button" data-admin-sidebar-toggle aria-controls="agency-sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
                    <x-icon name="menu" />
                </button>

                <form class="agency-topbar-search" method="GET" action="{{ $agencySearchRoute }}">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $agencySearchPlaceholder }}" aria-label="Rechercher">
                </form>

                <div class="agency-topbar-actions">
                    <x-notification-menu />
                    <details class="agency-profile-menu" data-disclosure-menu>
                        <summary aria-haspopup="true" aria-expanded="false">
                            <span class="agency-profile-avatar">{{ $agencyInitials ?: 'AA' }}</span>
                            <span class="agency-profile-copy"><strong>{{ $agencyUser->name }}</strong><small>{{ $agencyUser->role === \App\Models\User::ROLE_EMPLOYE ? 'Employé' : 'Admin Agence' }}</small><em>{{ $agency->nom }}</em></span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="agency-profile-dropdown">
                            <span><strong>{{ $agency->nom }}</strong><small>{{ $agency->ville ?: 'Agence GLV' }}</small></span>
                            <a href="{{ route('agence.profile.edit') }}"><x-icon name="users" /> Mon profil</a>
                            @if ($agencyUser->role === \App\Models\User::ROLE_ADMIN_AGENCE)<a href="{{ route('agence.settings.general') }}"><x-icon name="settings" /> Paramètres</a>@endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><x-icon name="logout" /> Se déconnecter</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            <main @class(['agency-content', 'agency-settings-content' => request()->routeIs('agence.settings.*', 'agence.profile.*')])>
                @if (session('success'))
                    <div class="agency-flash" role="status" data-flash-message>
                        <span><x-icon name="check" /></span>
                        {{ session('success') }}
                        <button type="button" data-flash-close aria-label="Fermer"><x-icon name="close" /></button>
                    </div>
                @endif
                @if (session('warning'))
                    <div class="agency-flash warning" role="alert" data-flash-message>
                        <span><x-icon name="info" /></span>
                        {{ session('warning') }}
                        <button type="button" data-flash-close aria-label="Fermer"><x-icon name="close" /></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
