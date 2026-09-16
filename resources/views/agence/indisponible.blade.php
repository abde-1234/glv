<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès indisponible — GLV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="placeholder-page">
    <main class="placeholder-card status-card">
        <div class="status-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <span class="placeholder-badge">{{ $agence->nom }}</span>
        <h1>Accès {{ $raison }}</h1>
        <p>L’accès à votre agence est actuellement {{ $raison }}. Contactez l’administrateur GLV pour régulariser votre situation.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="secondary-button">Se déconnecter</button>
        </form>
    </main>
</body>
</html>
