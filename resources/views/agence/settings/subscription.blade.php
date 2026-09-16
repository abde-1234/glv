@extends('layouts.agence')
@section('title', 'Mon abonnement')
@section('content')
    @php
        $statusLabels = ['actif' => 'Actif', 'essai' => 'Période d’essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'];
        $formattedAmount = $agence->montant_abonnement === null
            ? 'Non renseigné'
            : number_format((float) $agence->montant_abonnement, 2, ',', ' ').' MAD';
    @endphp

    <header class="agency-page-heading settings-page-heading">
        <div><h1>Mon abonnement</h1><p>Consultez les détails de votre abonnement et sa période de validité.</p></div>
    </header>

    <div @class(['agency-settings-layout', 'subscription-standalone' => ! $canManageAgency])>
        @if ($canManageAgency)<x-agency-settings-nav />@endif
        <div class="settings-stack agency-subscription-page">
            <section class="agency-card subscription-summary-card">
                <header class="subscription-summary-head">
                    <div class="subscription-plan-mark" aria-hidden="true"><x-icon name="card" width="24" height="24" /></div>
                    <div><small>Plan actuel</small><h2>{{ $agence->type_abonnement ?: 'Non défini' }}</h2><p>{{ $agence->nom }}</p></div>
                    <x-status-badge type="agency" :status="$displayStatus" />
                </header>

                <dl class="subscription-metrics">
                    <div><dt>Date de début</dt><dd>{{ $agence->date_debut_abonnement?->format('d/m/Y') ?? 'Non définie' }}</dd></div>
                    <div><dt>Date d’expiration</dt><dd>{{ $agence->date_expiration?->format('d/m/Y') ?? 'Sans échéance' }}</dd></div>
                    <div><dt>Jours restants</dt><dd>{{ $remainingDays === null ? 'Sans échéance' : $remainingDays.' jour(s)' }}</dd></div>
                    <div><dt>Montant</dt><dd>{{ $formattedAmount }}</dd></div>
                </dl>

                @if ($periodRemainingPercentage !== null)
                    <div @class(['subscription-progress', 'is-warning' => $agence->isSubscriptionExpiringSoon(), 'is-danger' => in_array($displayStatus, ['expire', 'suspendu'], true)]) aria-label="{{ $periodRemainingPercentage }} % de la période restante">
                        <span><strong>Validité restante</strong><b>{{ $periodRemainingPercentage }} %</b></span>
                        <div><i style="width: {{ $periodRemainingPercentage }}%"></i></div>
                    </div>
                @endif
                <p class="subscription-created">Agence créée le {{ $agence->created_at->format('d/m/Y') }}</p>
            </section>

            @if ($agence->isSubscriptionSuspended())
                <section class="subscription-state-card is-danger" role="status">
                    <x-icon name="pause" width="22" height="22" />
                    <div><h2>Agence suspendue</h2><p>Votre accès aux fonctionnalités GLV est temporairement suspendu.</p></div>
                </section>
            @elseif ($agence->isSubscriptionExpired())
                <section class="subscription-state-card is-danger" role="status">
                    <x-icon name="close" width="22" height="22" />
                    <div><h2>Abonnement expiré</h2><p>Votre abonnement a expiré. Contactez l’administrateur GLV pour le renouveler.</p></div>
                </section>
            @elseif ($agence->isSubscriptionExpiringSoon())
                <section class="subscription-state-card is-warning" role="status">
                    <x-icon name="clock" width="22" height="22" />
                    <div><h2>Votre abonnement expire bientôt.</h2><p>Il vous reste {{ $remainingDays }} jour(s), jusqu’au {{ $agence->date_expiration->format('d/m/Y') }}.</p></div>
                    <a href="#renouvellement">Renouveler mon abonnement</a>
                </section>
            @elseif ($agence->isSubscriptionTrial())
                <section class="subscription-state-card is-trial" role="status">
                    <x-icon name="clock" width="22" height="22" />
                    <div><h2>Période d’essai</h2><p>Votre agence conserve l’accès aux modules pendant encore {{ $remainingDays }} jour(s).</p></div>
                </section>
            @elseif ($agence->isSubscriptionActive())
                <section class="subscription-state-card is-success" role="status">
                    <x-icon name="check" width="22" height="22" />
                    <div><h2>Votre abonnement est actif.</h2><p>Vous pouvez accéder à tous les modules métier de GLV.</p></div>
                </section>
            @else
                <section class="subscription-state-card is-warning" role="status">
                    <x-icon name="info" width="22" height="22" />
                    <div><h2>Abonnement à régulariser</h2><p>Contactez l’administrateur de la plateforme pour finaliser votre abonnement.</p></div>
                </section>
            @endif

            <div class="subscription-lower-grid">
                <section class="agency-card settings-panel subscription-features">
                    <h2>Fonctionnalités du plan</h2>
                    <p>Votre abonnement donne accès aux modules disponibles dans GLV.</p>
                    <ul>
                        @foreach (['Gestion des voitures', 'Gestion des clients', 'Réservations', 'Contrats', 'Documents', 'Utilisateurs de l’agence'] as $feature)
                            <li><x-icon name="check" width="15" height="15" /> {{ $feature }}</li>
                        @endforeach
                    </ul>
                </section>

                <section class="agency-card settings-panel subscription-contact" id="renouvellement">
                    <h2>Contacter l’administrateur</h2>
                    <p>Contactez l’administrateur GLV pour renouveler ou modifier votre abonnement.</p>
                    @if ($supportEmail || $supportPhone)
                        <div class="subscription-contact-actions">
                            @if ($supportEmail)<a href="mailto:{{ $supportEmail }}"><x-icon name="mail" width="17" height="17" /> {{ $supportEmail }}</a>@endif
                            @if ($supportPhone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $supportPhone) }}"><x-icon name="phone" width="17" height="17" /> {{ $supportPhone }}</a>@endif
                        </div>
                    @else
                        <p class="subscription-contact-fallback">Aucune coordonnée de support n’est encore configurée. Contactez l’administrateur de la plateforme.</p>
                    @endif

                    @if (in_array($displayStatus, ['expire', 'suspendu'], true))
                        <div class="subscription-account-actions">
                            <a href="{{ route('agence.settings.subscription') }}">Mon abonnement</a>
                            <a href="{{ route('agence.profile.edit') }}">Mon profil</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Se déconnecter</button></form>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection
