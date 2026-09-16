<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Voiture;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalVoitures = Voiture::count();
        $totalClients = Client::count();
        $totalReservations = Reservation::count();
        $chiffreAffaires = (float) Reservation::sum('montant');

        $statusDistribution = [
            'disponible' => Voiture::where('statut', 'disponible')->count(),
            'loue' => Voiture::where('statut', 'loue')->count(),
            'maintenance' => Voiture::where('statut', 'maintenance')->count(),
            'indisponible' => Voiture::where('statut', 'indisponible')->count(),
        ];

        $today = CarbonImmutable::today();
        $periodStart = $today->subDays(29);
        $reservationDates = Reservation::query()
            ->whereDate('date_debut', '>=', $periodStart)
            ->pluck('date_debut')
            ->map(fn ($date) => CarbonImmutable::parse($date));

        $trendLabels = [];
        $reservationTrend = [];

        foreach (range(0, 5) as $bucket) {
            $start = $periodStart->addDays($bucket * 5);
            $end = $start->addDays(4)->min($today);
            $trendLabels[] = $start->locale('fr')->translatedFormat('d M');
            $reservationTrend[] = $reservationDates
                ->filter(fn (CarbonImmutable $date) => $date->betweenIncluded($start, $end))
                ->count();
        }

        $recentReservations = Reservation::query()
            ->with(['client', 'voiture'])
            ->latest('date_debut')
            ->latest('id')
            ->limit(5)
            ->get();
        $agence = auth()->user()->agence;

        return view('agence.dashboard', compact(
            'agence',
            'totalVoitures',
            'totalClients',
            'totalReservations',
            'chiffreAffaires',
            'statusDistribution',
            'trendLabels',
            'reservationTrend',
            'recentReservations',
        ));
    }
}
