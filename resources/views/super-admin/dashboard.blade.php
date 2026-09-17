@extends('layouts.super-admin')

@section('title', 'Tableau de bord')

@section('content')
    @php
        $chartMax = max(max($monthlyReservations), 1);
        $chartPoints = collect($monthlyReservations)->map(function ($value, $index) use ($chartMax) {
            $x = $index * 100;
            $y = 210 - (($value / $chartMax) * 165);
            return "{$x},{$y}";
        })->implode(' ');
        $areaPoints = "0,230 {$chartPoints} 1100,230";
        $distributionTotal = max(array_sum($subscriptionDistribution), 1);
        $activeEnd = ($subscriptionDistribution['actif'] / $distributionTotal) * 360;
        $trialEnd = $activeEnd + (($subscriptionDistribution['essai'] / $distributionTotal) * 360);
        $expiredEnd = $trialEnd + (($subscriptionDistribution['expire'] / $distributionTotal) * 360);
    @endphp

    <div class="page-heading dashboard-heading">
        <div>
            <p class="page-eyebrow">Vue d’ensemble</p>
            <h1>Tableau de bord Super Admin</h1>
            <p>Suivez l’activité globale de votre plateforme GLV.</p>
        </div>
        <div class="date-chip">
            <span><x-icon name="calendar" /></span>
            <span><strong>{{ now()->locale('fr')->translatedFormat('l d F Y') }}</strong><small>Mise à jour en temps réel</small></span>
        </div>
    </div>

    <section class="kpi-grid" aria-label="Indicateurs clés">
        <article class="kpi-card">
            <span class="kpi-icon blue"><x-icon name="building" /></span>
            <div><p>Agences actives</p><strong>{{ number_format($activeAgencies, 0, ',', ' ') }}</strong><small><span>↗</span> réseau GLV</small></div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon indigo"><x-icon name="car" /></span>
            <div><p>Voitures totales</p><strong>{{ number_format($totalCars, 0, ',', ' ') }}</strong><small><span>↗</span> toutes agences</small></div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon cyan"><x-icon name="calendar" /></span>
            <div><p>Réservations globales</p><strong>{{ number_format($totalReservations, 0, ',', ' ') }}</strong><small><span>↗</span> activité cumulée</small></div>
        </article>
        <article class="kpi-card">
            <span class="kpi-icon green"><x-icon name="users" /></span>
            <div><p>Abonnements actifs</p><strong>{{ number_format($activeSubscriptions, 0, ',', ' ') }}</strong><small><span>↗</span> à jour</small></div>
        </article>
    </section>

    @php
        $attentionTotal = array_sum($subscriptionAttention);
    @endphp
    <section class="content-card dashboard-subscription-attention">
        <div><span class="section-icon"><x-icon name="bell" /></span><span><small>Abonnements à surveiller</small><strong>{{ $attentionTotal }} agence(s) nécessitent votre attention</strong></span></div>
        <p><b>{{ $subscriptionAttention['expired'] }}</b> expiré(s) · <b>{{ $subscriptionAttention['tomorrow'] }}</b> expire(nt) demain · <b>{{ $subscriptionAttention['pending'] }}</b> demande(s) en attente</p>
        <a class="primary-button" href="{{ route('super-admin.abonnements.index') }}">Voir les abonnements</a>
    </section>

    <section class="dashboard-chart-grid">
        <article class="content-card chart-card">
            <div class="card-heading">
                <div><span class="section-icon"><x-icon name="chart" /></span><h2>Aperçu des réservations</h2></div>
                <span class="subtle-select">{{ now()->year }}</span>
            </div>
            <div class="line-chart" role="img" aria-label="Réservations mensuelles pour {{ now()->year }}">
                <svg viewBox="0 0 1100 250" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="chartArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1677ff" stop-opacity=".24"/>
                            <stop offset="100%" stop-color="#1677ff" stop-opacity=".02"/>
                        </linearGradient>
                    </defs>
                    <g class="chart-grid-lines">
                        <line x1="0" y1="45" x2="1100" y2="45"/><line x1="0" y1="100" x2="1100" y2="100"/>
                        <line x1="0" y1="155" x2="1100" y2="155"/><line x1="0" y1="210" x2="1100" y2="210"/>
                    </g>
                    <polygon points="{{ $areaPoints }}" fill="url(#chartArea)"/>
                    <polyline points="{{ $chartPoints }}" fill="none" stroke="#1677ff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                    @foreach ($monthlyReservations as $index => $value)
                        @php
                            $x = $index * 100;
                            $y = 210 - (($value / $chartMax) * 165);
                        @endphp
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="6" fill="#fff" stroke="#1677ff" stroke-width="4" vector-effect="non-scaling-stroke"><title>{{ $monthLabels[$index] }} : {{ $value }}</title></circle>
                    @endforeach
                </svg>
                <div class="chart-labels">
                    @foreach ($monthLabels as $label)<span>{{ $label }}</span>@endforeach
                </div>
            </div>
        </article>

        <article class="content-card distribution-card">
            <div class="card-heading">
                <div><span class="section-icon"><x-icon name="card" /></span><h2>Répartition des abonnements</h2></div>
            </div>
            <div class="distribution-content">
                <div class="donut-chart" style="background: conic-gradient(#21b979 0deg {{ $activeEnd }}deg, #4f9cf9 {{ $activeEnd }}deg {{ $trialEnd }}deg, #f25367 {{ $trialEnd }}deg {{ $expiredEnd }}deg, #f59e0b {{ $expiredEnd }}deg 360deg)">
                    <span><strong>{{ array_sum($subscriptionDistribution) }}</strong><small>au total</small></span>
                </div>
                <div class="distribution-legend">
                    @foreach ([
                        'actif' => ['Actif', 'green'],
                        'essai' => ['Essai', 'blue'],
                        'expire' => ['Expiré', 'red'],
                        'suspendu' => ['Suspendu', 'orange'],
                    ] as $status => [$label, $color])
                        <div><span class="legend-dot {{ $color }}"></span><span><strong>{{ $label }}</strong><small>{{ round(($subscriptionDistribution[$status] / $distributionTotal) * 100) }}%</small></span><b>{{ $subscriptionDistribution[$status] }}</b></div>
                    @endforeach
                </div>
            </div>
        </article>
    </section>

    <section class="dashboard-bottom-grid">
        <article class="content-card recent-card">
            <div class="card-heading">
                <div><span class="section-icon"><x-icon name="building" /></span><h2>Agences récentes</h2></div>
                <a href="{{ route('super-admin.agences.index') }}">Voir toutes les agences <x-icon name="arrow-right" /></a>
            </div>
            <div class="table-scroll">
                <table class="data-table compact-table">
                    <thead><tr><th>Agence</th><th>Ville</th><th>Téléphone</th><th>Statut</th><th>Création</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($recentAgencies as $agence)
                        <tr>
                            <td><div class="agency-cell"><span class="agency-avatar">{{ str($agence->nom)->substr(0, 2)->upper() }}</span><span><strong>{{ $agence->nom }}</strong><small>{{ $agence->primaryAdmin?->name ?? 'Aucun gérant' }}</small></span></div></td>
                            <td>{{ $agence->ville ?: '—' }}</td>
                            <td>{{ $agence->telephone ?: '—' }}</td>
                            <td><span class="status-badge {{ $agence->statut }}"><i></i>{{ ucfirst($agence->statut) }}</span></td>
                            <td>{{ $agence->created_at->format('d/m/Y') }}</td>
                            <td><a class="icon-link" href="{{ route('super-admin.agences.show', $agence) }}" aria-label="Voir {{ $agence->nom }}"><x-icon name="arrow-right" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state">Aucune agence enregistrée pour le moment.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="content-card quick-actions-card">
            <div class="card-heading"><div><span class="section-icon"><x-icon name="plus" /></span><h2>Actions rapides</h2></div></div>
            <div class="quick-actions">
                <a class="primary" href="{{ route('super-admin.agences.create') }}"><span><x-icon name="plus" /> Ajouter une agence</span><x-icon name="arrow-right" /></a>
                <a href="{{ route('super-admin.abonnements.index') }}"><span><x-icon name="card" /> Gérer les abonnements</span><x-icon name="arrow-right" /></a>
                <a href="{{ route('super-admin.agences.index', ['statut' => 'suspendu']) }}"><span><x-icon name="pause" /> Agences suspendues</span><x-icon name="arrow-right" /></a>
            </div>
            <div class="tip-box"><x-icon name="info" /><span><strong>Astuce</strong><small>Surveillez les périodes d’essai proches de leur expiration.</small></span></div>
        </aside>
    </section>
@endsection
