@php($authLocale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $authLocale }}" dir="{{ $authLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('auth.meta'))">
    <title>@yield('title', __('auth.page_title')) — GLV</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/glv-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page @yield('body-class')">
    <main class="auth-shell">
        <section class="brand-panel" aria-labelledby="brand-heading">
            <div class="brand-content">
                <a class="brand" href="{{ url('/') }}" aria-label="{{ __('auth.home_label') }}">
                    <img class="brand-logo" src="{{ asset('images/branding/glv-logo-final.png') }}" width="2172" height="724" alt="GLV — Location de voitures">
                </a>
                <div class="brand-message"><p class="brand-slogan"><span aria-hidden="true"></span>PLUS LOIN, ENSEMBLE</p><h1 id="brand-heading"><span>{{ __('auth.hero.line_1') }}</span><span>{{ __('auth.hero.line_2') }}</span><strong>{{ __('auth.hero.accent') }}</strong></h1><p class="brand-lead">{{ __('auth.lead') }}</p></div>
                <div class="benefits" aria-label="{{ __('auth.benefits_label') }}">
                    <div class="benefit"><span class="benefit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 21V6.5A1.5 1.5 0 0 1 5.5 5H15v16M8 9h3m-3 4h3m-3 4h3m4-7h3.5a1.5 1.5 0 0 1 1.5 1.5V21M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ __('auth.benefits.0.title') }}</strong><small>{{ __('auth.benefits.0.detail') }}</small></span></div>
                    <div class="benefit"><span class="benefit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 10 1.7-4h10.6l1.7 4m-14 0h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7" cy="14" r="1.2" fill="currentColor"/><circle cx="17" cy="14" r="1.2" fill="currentColor"/></svg></span><span><strong>{{ __('auth.benefits.1.title') }}</strong><small>{{ __('auth.benefits.1.detail') }}</small></span></div>
                    <div class="benefit"><span class="benefit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.4 2.7 8.3 7 10 4.3-1.7 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ __('auth.benefits.2.title') }}</strong><small>{{ __('auth.benefits.2.detail') }}</small></span></div>
                </div>
                <blockquote class="hero-quote">“{{ __('auth.quote.0') }}<br>{{ __('auth.quote.1') }}”</blockquote>
            </div>
            <div class="hero-visual" aria-hidden="true"><img src="{{ asset('images/glv-login-hero-cinematic-v2.png') }}" alt=""></div>
        </section>
        <section class="login-panel" aria-labelledby="auth-title">
            @hasSection('hide-language-selector')
            @else
            <div class="panel-topbar">
                <a class="mobile-brand" href="{{ url('/') }}" aria-label="{{ __('auth.home_label') }}"><img src="{{ asset('images/branding/glv-logo-final.png') }}" width="2172" height="724" alt="GLV — Location de voitures"></a>
                <div class="language-selector" data-language-selector>
                    <button class="language-button" type="button" aria-label="{{ __('auth.language_label') }}" aria-haspopup="menu" aria-expanded="false" aria-controls="language-menu" data-language-trigger>
                        <svg class="language-globe" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c2.2 2.5 3.3 5.5 3.3 9S14.2 18.5 12 21c-2.2-2.5-3.3-5.5-3.3-9S9.8 5.5 12 3Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        <span>{{ __('auth.languages.'.$authLocale) }}</span>
                        <svg class="language-chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="language-menu" id="language-menu" role="menu" aria-label="{{ __('auth.language_label') }}" data-language-menu hidden>
                        @foreach (['fr' => '🇫🇷', 'en' => '🇬🇧', 'ar' => '🇲🇦'] as $localeCode => $flag)
                            <form method="POST" action="{{ route('locale.update') }}" role="none">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $localeCode }}">
                                <button type="submit" role="menuitem" @if ($authLocale === $localeCode) aria-current="true" @endif>
                                    <span class="language-flag" aria-hidden="true">{{ $flag }}</span>
                                    <span lang="{{ $localeCode }}" dir="{{ $localeCode === 'ar' ? 'rtl' : 'ltr' }}">{{ __('auth.languages.'.$localeCode) }}</span>
                                    <svg class="language-check" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 8 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
            <div class="login-card @yield('card-class')">@yield('content')</div>
            <footer class="auth-footer"><span>© {{ date('Y') }} GLV. {{ __('auth.footer') }}</span><nav aria-label="{{ __('auth.footer_navigation') }}"><span>{{ __('auth.privacy') }}</span><span>{{ __('auth.terms') }}</span><span>{{ __('auth.help') }}</span></nav></footer>
        </section>
    </main>
</body>
</html>
