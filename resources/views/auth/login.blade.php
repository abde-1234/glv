<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Connexion à la plateforme de gestion de location automobile GLV.">
    <title>Connexion — GLV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="brand-panel" aria-labelledby="brand-heading">
            <div class="brand-content">
                <a class="brand" href="{{ url('/') }}" aria-label="GLV, accueil">
                    <span class="brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 52 36" fill="none">
                            <path d="M5 24.5h4.1l2.1-7.2a5 5 0 0 1 3.5-3.4l8.2-2.3a13 13 0 0 1 7 .1l6.3 2.1a8 8 0 0 1 4.1 3l2 2.7 4.8 1.2V27h-3.8" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13 20h25M20 14.1l-2.5 5.8M29.5 13.3l4.1 6.3" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>
                            <circle cx="15" cy="27" r="4.2" fill="white" stroke="currentColor" stroke-width="2.6"/>
                            <circle cx="39" cy="27" r="4.2" fill="white" stroke="currentColor" stroke-width="2.6"/>
                        </svg>
                    </span>
                    <span class="brand-copy">
                        <span class="brand-name">GLV</span>
                        <span class="brand-tagline">Location de voitures</span>
                    </span>
                </a>

                <div class="brand-message">
                    <span class="brand-accent" aria-hidden="true"></span>
                    <h1 id="brand-heading">
                        <span>La gestion de</span>
                        <span>location auto,</span>
                        <strong>simplement.</strong>
                    </h1>
                    <p class="brand-lead">Une plateforme simple pour gérer vos véhicules, réservations, clients et agences.</p>
                </div>

                <div class="benefits" aria-label="Avantages GLV">
                    <div class="benefit">
                        <span class="benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V6.5A1.5 1.5 0 0 1 5.5 5H15v16M8 9h3m-3 4h3m-3 4h3m4-7h3.5a1.5 1.5 0 0 1 1.5 1.5V21M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span><strong>Multi-agences</strong><small>Tout centraliser</small></span>
                    </div>
                    <div class="benefit">
                        <span class="benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m5 10 1.7-4h10.6l1.7 4m-14 0h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7" cy="14" r="1.2" fill="currentColor"/><circle cx="17" cy="14" r="1.2" fill="currentColor"/><path d="M5 17v2m14-2v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <span><strong>Gestion de flotte</strong><small>Claire et efficace</small></span>
                    </div>
                    <div class="benefit">
                        <span class="benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.4 2.7 8.3 7 10 4.3-1.7 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span><strong>Simple et sécurisé</strong><small>Vos données protégées</small></span>
                    </div>
                </div>

                <blockquote class="hero-quote">“Plus de mobilité,<br>moins de complexité.”</blockquote>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <img src="{{ asset('images/glv-login-hero.png') }}" alt="">
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="panel-topbar">
                <a class="mobile-brand" href="{{ url('/') }}" aria-label="GLV, accueil">
                    <span class="brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 52 36" fill="none">
                            <path d="M5 24.5h4.1l2.1-7.2a5 5 0 0 1 3.5-3.4l8.2-2.3a13 13 0 0 1 7 .1l6.3 2.1a8 8 0 0 1 4.1 3l2 2.7 4.8 1.2V27h-3.8" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="15" cy="27" r="4.2" fill="white" stroke="currentColor" stroke-width="2.6"/><circle cx="39" cy="27" r="4.2" fill="white" stroke="currentColor" stroke-width="2.6"/>
                        </svg>
                    </span>
                    <span class="brand-name">GLV</span>
                </a>
                <span class="language" aria-label="Langue française">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c2.2 2.5 3.3 5.5 3.3 9S14.2 18.5 12 21c-2.2-2.5-3.3-5.5-3.3-9S9.8 5.5 12 3Z" stroke="currentColor" stroke-width="1.7"/></svg>
                    Français
                    <svg class="language-chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </div>

            <div class="login-card">
                <div class="login-heading">
                    <span class="lock-mark" aria-hidden="true">
                        <svg viewBox="0 0 28 28" fill="none"><rect x="5" y="12" width="18" height="13" rx="3" fill="currentColor"/><path d="M9 12V9a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M14 17v4" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <h2 id="login-title">Connexion</h2>
                    <p>Accédez à votre espace GLV</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="login-form" novalidate>
                    @csrf

                    <div class="field-group">
                        <label for="email">Email</label>
                        <div class="input-wrap @error('email') has-error @enderror">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="votre@email.com" required autofocus aria-describedby="email-error">
                        </div>
                        @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field-group">
                        <label for="password">Mot de passe</label>
                        <div class="input-wrap @error('password') has-error @enderror">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Votre mot de passe" required aria-describedby="password-error">
                            <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Afficher le mot de passe">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 4 16 16M9.8 6.3A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a18 18 0 0 1-2.4 3.1M6.2 7.6A18.2 18.2 0 0 0 2.5 12s3.5 6 9.5 6c1 0 2-.2 2.8-.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-options">
                        <label class="remember-option">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span class="custom-checkbox" aria-hidden="true">
                                <svg viewBox="0 0 16 16" fill="none"><path d="m4 8 2.5 2.5L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            Se souvenir de moi
                        </label>
                        <span class="forgot-link" role="link" aria-disabled="true" title="Fonctionnalité bientôt disponible">Mot de passe oublié&nbsp;?</span>
                    </div>

                    <button class="login-button" type="submit">
                        <span>Se connecter</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </form>

                <div class="auth-separator" aria-hidden="true"><span>Ou</span></div>

                <div class="info-note access-note">
                    <span class="info-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5-1a3 3 0 0 0 0-5.8M21 20v-1.5a4 4 0 0 0-3-3.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <span><strong>Accès Super Admin et Admin Agence</strong><small>Une seule plateforme pour tous vos besoins.</small></span>
                </div>

                <div class="info-note security-note">
                    <span class="info-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.4 2.7 8.3 7 10 4.3-1.7 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span><strong>Plateforme sécurisée</strong><small>Vos données sont protégées et confidentielles.</small></span>
                </div>
            </div>

            <footer class="auth-footer">© {{ date('Y') }} GLV. Tous droits réservés.</footer>
        </section>
    </main>
</body>
</html>
