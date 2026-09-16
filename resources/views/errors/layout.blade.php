<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — GLV</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f4f7fb; color: #082a46; font: 15px/1.6 system-ui, sans-serif; }
        main { width: 100%; max-width: 520px; padding: 32px; border: 1px solid #e3eaf3; border-radius: 12px; background: white; text-align: center; box-shadow: 0 12px 36px #082a460a; overflow-wrap: anywhere; }
        .brand { font-size: 22px; font-weight: 800; letter-spacing: -1px; color: #1677ff; }
        .code { width: 64px; height: 64px; margin: 24px auto 16px; display: grid; place-items: center; border-radius: 12px; color: #1677ff; background: #eaf3ff; font-size: 21px; font-weight: 700; }
        h1 { margin: 0; font-size: 28px; line-height: 1.3; }
        p { margin: 14px 0 24px; color: #60748a; }
        nav { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        a, button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; max-width: 100%; padding: 10px 17px; border: 1px solid #dbe6f3; border-radius: 8px; font: inherit; text-decoration: none; color: #082a46; background: #fff; cursor: pointer; }
        a { color: white; background: #1677ff; border-color: #1677ff; }
        a:focus-visible, button:focus-visible { outline: 3px solid #82b9ff; outline-offset: 3px; }
        @media (max-width: 480px) { body { padding: 16px; } main { padding: 24px 18px; } nav > * { width: 100%; } }
    </style>
</head>
<body>
    @php
        $dashboardUrl = match (auth()->user()?->role) {
            \App\Models\User::ROLE_SUPER_ADMIN => route('super-admin.dashboard'),
            \App\Models\User::ROLE_ADMIN_AGENCE, \App\Models\User::ROLE_EMPLOYE => route('dashboard'),
            default => route('login'),
        };
    @endphp
    <main>
        <div class="brand">GLV</div>
        <div class="code" aria-hidden="true">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <nav aria-label="Navigation de secours">
            @hasSection('back')<button type="button" onclick="if (history.length > 1) history.back(); else location.assign('/')">Retour</button>@endif
            <a href="{{ $dashboardUrl }}">@yield('action', 'Tableau de bord')</a>
        </nav>
    </main>
</body>
</html>
