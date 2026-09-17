<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\RenewalRequest;
use App\Models\Reservation;
use App\Models\Voiture;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $yearReservations = Reservation::query()
            ->whereBetween('date_debut', [$today->copy()->startOfYear(), $today->copy()->endOfYear()])
            ->get(['date_debut']);

        $monthlyReservations = collect(range(1, 12))
            ->map(fn (int $month): int => $yearReservations
                ->filter(fn (Reservation $reservation): bool => $reservation->date_debut->month === $month)
                ->count())
            ->all();

        $subscriptionCounts = Agence::query()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return view('super-admin.dashboard', [
            'activeAgencies' => Agence::where('statut', 'actif')->count(),
            'totalCars' => Voiture::count(),
            'totalReservations' => Reservation::count(),
            'activeSubscriptions' => Agence::query()
                ->where('statut', 'actif')
                ->where(fn ($query) => $query
                    ->whereNull('date_expiration')
                    ->orWhereDate('date_expiration', '>=', $today))
                ->count(),
            'recentAgencies' => Agence::with('primaryAdmin')->latest()->limit(5)->get(),
            'monthlyReservations' => $monthlyReservations,
            'monthLabels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            'subscriptionDistribution' => [
                'actif' => (int) ($subscriptionCounts['actif'] ?? 0),
                'essai' => (int) ($subscriptionCounts['essai'] ?? 0),
                'expire' => (int) ($subscriptionCounts['expire'] ?? 0),
                'suspendu' => (int) ($subscriptionCounts['suspendu'] ?? 0),
            ],
            'subscriptionAttention' => [
                'expired' => Agence::query()->withSubscriptionStatus('expire')->count(),
                'tomorrow' => Agence::query()->whereNotIn('statut', ['suspendu', 'expire'])->whereDate('date_expiration', $today->copy()->addDay())->count(),
                'pending' => RenewalRequest::query()->where('status', RenewalRequest::PENDING)->count(),
            ],
        ]);
    }
}
