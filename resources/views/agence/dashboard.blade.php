@extends('layouts.agence')

@section('title', 'Dashboard Agence')

@section('content')
    @php
        $firstName = str(auth()->user()->name)->before(' ');
        $chartMax = max(max($reservationTrend), 1);
        $pointCount = max(count($reservationTrend) - 1, 1);
        $chartPoints = collect($reservationTrend)->map(function ($value, $index) use ($chartMax, $pointCount) {
            $x = $index * (700 / $pointCount);
            $y = 145 - (($value / $chartMax) * 115);
            return round($x, 2).','.round($y, 2);
        })->implode(' ');
        $areaPoints = "0,160 {$chartPoints} 700,160";
        $vehicleTotal = max(array_sum($statusDistribution), 1);
        $availableEnd = ($statusDistribution['disponible'] / $vehicleTotal) * 360;
        $rentedEnd = $availableEnd + (($statusDistribution['loue'] / $vehicleTotal) * 360);
        $maintenanceEnd = $rentedEnd + (($statusDistribution['maintenance'] / $vehicleTotal) * 360);
    @endphp

    <header class="agency-page-heading agency-dashboard-heading">
        <div>
            <h1>Bonjour {{ $firstName }} !</h1>
            <p>Voici un aperçu de l’activité de votre agence.</p>
        </div>
        <div class="agency-date-chip">
            <span><x-icon name="calendar" /></span>
            <span><small>Aujourd’hui</small><strong>{{ now()->locale('fr')->translatedFormat('D. d M. Y') }}</strong></span>
        </div>
    </header>

    @if ($agence->isSubscriptionExpiringSoon())
        <aside class="agency-subscription-banner is-warning" role="status">
            <x-icon name="clock" width="18" height="18" />
            <span>Votre abonnement expire dans {{ $agence->subscriptionDaysRemaining() }} jour(s).</span>
            <a href="{{ route('agence.settings.subscription') }}#renouvellement">Renouveler</a>
        </aside>
    @elseif ($agence->isSubscriptionTrial())
        <aside class="agency-subscription-banner" role="status">
            <x-icon name="info" width="18" height="18" />
            <span>{{ $agence->subscriptionDaysRemaining() <= 7 ? 'Votre période d’essai arrive bientôt à expiration.' : 'Période d’essai' }} — {{ $agence->subscriptionDaysRemaining() }} jour(s) restant(s).</span>
            <a href="{{ route('agence.settings.subscription') }}">Voir l’abonnement</a>
        </aside>
    @endif

    <section class="agency-card dashboard-subscription-card">
        <div><small>Votre abonnement</small><h2>{{ $agence->type_abonnement ?: 'Non défini' }}</h2></div>
        <x-status-badge type="agency" :status="$agence->subscriptionStatus()" />
        <p>{{ $agence->date_expiration ? 'Expire le '.$agence->date_expiration->format('d/m/Y') : 'Sans échéance' }}</p>
        <strong>{{ $agence->subscriptionDaysRemaining() === null ? 'Durée illimitée' : $agence->subscriptionDaysRemaining().' jours restants' }}</strong>
        <a href="{{ route('agence.settings.subscription') }}">Gérer mon abonnement <x-icon name="arrow-right" /></a>
    </section>

    <section class="agency-kpi-grid" aria-label="Indicateurs clés">
        <article class="agency-kpi-card">
            <span class="agency-kpi-icon blue"><x-icon name="car" /></span>
            <div><p>Voitures totales</p><strong>{{ number_format($totalVoitures, 0, ',', ' ') }}</strong><small><i></i> flotte de l’agence</small></div>
        </article>
        <article class="agency-kpi-card">
            <span class="agency-kpi-icon green"><x-icon name="users" /></span>
            <div><p>Clients</p><strong>{{ number_format($totalClients, 0, ',', ' ') }}</strong><small><i></i> base clients</small></div>
        </article>
        <article class="agency-kpi-card">
            <span class="agency-kpi-icon cyan"><x-icon name="calendar" /></span>
            <div><p>Réservations</p><strong>{{ number_format($totalReservations, 0, ',', ' ') }}</strong><small><i></i> activité cumulée</small></div>
        </article>
        <article class="agency-kpi-card">
            <span class="agency-kpi-icon orange"><x-icon name="card" /></span>
            <div><p>Chiffre d’affaires</p><strong>{{ number_format($chiffreAffaires, 0, ',', ' ') }} <b>MAD</b></strong><small><i></i> réservations</small></div>
        </article>
    </section>

    <section class="agency-chart-grid">
        <article class="agency-card">
            <div class="agency-card-heading">
                <h2>Réservations des 30 derniers jours</h2>
                <span>30 derniers jours <x-icon name="chevron-down" /></span>
            </div>
            <div class="agency-line-chart" role="img" aria-label="Évolution des réservations des 30 derniers jours">
                <svg viewBox="0 0 700 170" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="agencyChartArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1677ff" stop-opacity=".24"/>
                            <stop offset="100%" stop-color="#1677ff" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>
                    <g class="agency-chart-lines"><line x1="0" y1="30" x2="700" y2="30"/><line x1="0" y1="88" x2="700" y2="88"/><line x1="0" y1="145" x2="700" y2="145"/></g>
                    <polygon points="{{ $areaPoints }}" fill="url(#agencyChartArea)"/>
                    <polyline points="{{ $chartPoints }}" fill="none" stroke="#1677ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                    @foreach ($reservationTrend as $index => $value)
                        @php
                            $x = $index * (700 / $pointCount);
                            $y = 145 - (($value / $chartMax) * 115);
                        @endphp
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#fff" stroke="#1677ff" stroke-width="3" vector-effect="non-scaling-stroke"><title>{{ $trendLabels[$index] }} : {{ $value }}</title></circle>
                    @endforeach
                </svg>
                <div class="agency-chart-labels">@foreach ($trendLabels as $label)<span>{{ $label }}</span>@endforeach</div>
            </div>
        </article>

        <article class="agency-card">
            <div class="agency-card-heading"><h2>Statut des voitures</h2></div>
            <div class="agency-distribution">
                <div class="agency-donut" style="background: conic-gradient(#19b978 0deg {{ $availableEnd }}deg, #2e8bf5 {{ $availableEnd }}deg {{ $rentedEnd }}deg, #f59e0b {{ $rentedEnd }}deg {{ $maintenanceEnd }}deg, #ef4056 {{ $maintenanceEnd }}deg 360deg)">
                    <span><strong>{{ array_sum($statusDistribution) }}</strong><small>voitures</small></span>
                </div>
                <div class="agency-distribution-legend">
                    @foreach ([
                        'disponible' => ['Disponibles', 'green'],
                        'loue' => ['Louées', 'blue'],
                        'maintenance' => ['Maintenance', 'orange'],
                        'indisponible' => ['Indisponibles', 'red'],
                    ] as $status => [$label, $color])
                        <div><i class="{{ $color }}"></i><span>{{ $label }}</span><b>{{ $statusDistribution[$status] }}</b></div>
                    @endforeach
                </div>
            </div>
        </article>
    </section>

    <section class="agency-bottom-grid">
        <article class="agency-card">
            <div class="agency-card-heading">
                <h2>Dernières réservations</h2>
                <a class="agency-heading-link" href="{{ route('agence.reservations.index') }}">Voir toutes <x-icon name="arrow-right" /></a>
            </div>
            <div class="agency-table-scroll">
                <table class="agency-table agency-compact-table">
                    <thead><tr><th>Client</th><th>Voiture</th><th>Début</th><th>Fin</th><th>Montant</th><th>Statut</th></tr></thead>
                    <tbody>
                    @forelse ($recentReservations as $reservation)
                        <tr>
                            <td><strong>{{ $reservation->client?->nom ?? 'Client supprimé' }}</strong></td>
                            <td>{{ trim(($reservation->voiture?->marque ?? '').' '.($reservation->voiture?->modele ?? '')) ?: 'Voiture supprimée' }}</td>
                            <td>{{ $reservation->date_debut->format('d/m/Y') }}</td>
                            <td>{{ $reservation->date_fin->format('d/m/Y') }}</td>
                            <td><strong>{{ number_format((float) $reservation->montant, 0, ',', ' ') }} MAD</strong></td>
                            <td><span class="reservation-status is-{{ str($reservation->statut)->slug() }}">{{ str($reservation->statut)->replace('_', ' ')->title() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="agency-empty-state"><x-icon name="calendar" /><strong>Aucune réservation récente</strong><span>Les nouvelles réservations apparaîtront ici.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="agency-card agency-quick-card">
            <div class="agency-card-heading"><h2>Actions rapides</h2></div>
            <div class="agency-quick-actions">
                <a class="primary" href="{{ route('agence.voitures.create') }}"><span><x-icon name="plus" /> Ajouter une voiture</span><x-icon name="arrow-right" /></a>
                <a href="{{ route('agence.reservations.create') }}"><span><x-icon name="calendar" /> Nouvelle réservation</span><x-icon name="arrow-right" /></a>
                <a href="{{ route('agence.clients.create') }}"><span><x-icon name="users" /> Ajouter un client</span><x-icon name="arrow-right" /></a>
                <a href="{{ route('agence.contrats.create') }}"><span><x-icon name="card" /> Créer un contrat</span><x-icon name="arrow-right" /></a>
            </div>
        </aside>
    </section>
@endsection
