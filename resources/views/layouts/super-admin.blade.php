<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Espace de supervision GLV.">
    <title>@yield('title', 'Super Admin') — GLV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar aria-label="Navigation principale">
            <a class="sidebar-brand" href="{{ route('super-admin.dashboard') }}">
                <span class="sidebar-car" aria-hidden="true"><x-icon name="car" /></span>
                <span><strong>GLV</strong><small>Plateforme Multi-Agences</small></span>
            </a>

            <nav class="sidebar-nav">
                <a href="{{ route('super-admin.dashboard') }}" @class(['active' => request()->routeIs('super-admin.dashboard')])>
                    <x-icon name="home" /> <span>Tableau de bord</span>
                </a>
                <a href="{{ route('super-admin.agences.index') }}" @class(['active' => request()->routeIs('super-admin.agences.*')])>
                    <x-icon name="building" /> <span>Agences</span>
                </a>
                <a href="{{ route('super-admin.abonnements.index') }}" @class(['active' => request()->routeIs('super-admin.abonnements.*')])>
                    <x-icon name="card" /> <span>Abonnements</span>
                </a>
            </nav>

            <div class="sidebar-spacer"></div>

            <div class="sidebar-visual" aria-hidden="true">
                <img src="{{ asset('images/glv-login-hero.png') }}" alt="">
                <p>Des agences plus fortes,<br>une mobilité plus simple.</p>
            </div>

            <nav class="sidebar-nav sidebar-nav-bottom">
                <a href="{{ route('super-admin.settings.edit') }}" @class(['active' => request()->routeIs('super-admin.settings.*')])><x-icon name="settings" /> <span>Paramètres</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><x-icon name="logout" /> <span>Déconnexion</span></button>
                </form>
            </nav>
        </aside>

        <button class="sidebar-backdrop" type="button" data-admin-sidebar-close aria-label="Fermer le menu"></button>

        <div class="admin-main">
            <header class="admin-topbar">
                <button class="topbar-menu" type="button" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
                    <x-icon name="menu" />
                </button>

                <form class="topbar-search" method="GET" action="{{ route('super-admin.agences.index') }}">
                    <x-icon name="search" />
                    <input type="search" name="q" placeholder="Rechercher une agence, un gérant…" aria-label="Rechercher une agence">
                </form>

                <div class="topbar-actions">
                    <x-notification-menu />
                    <details class="profile-menu">
                        <summary>
                            <span class="profile-avatar">SA</span>
                            <span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>Administrateur global</small></span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="profile-dropdown">
                            <a href="{{ route('super-admin.settings.edit') }}#compte"><x-icon name="settings" /> Mon compte</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><x-icon name="logout" /> Se déconnecter</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            <main class="admin-content">
                @if (session('success'))
                    <div class="flash-message" role="status" data-flash-message>
                        <span><x-icon name="check" /></span>
                        {{ session('success') }}
                        <button type="button" data-flash-close aria-label="Fermer"><x-icon name="close" /></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
