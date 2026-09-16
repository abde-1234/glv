<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Voiture;
use App\Support\AgencyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $agenceId = (int) $request->user()->agence_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::in(array_keys($this->statuses()))],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $reservations = Reservation::query()
            ->where('agence_id', $agenceId)
            ->with(['client', 'voiture'])
            ->when($filters['q'] ?? null, function ($query, string $search) use ($agenceId): void {
                $referenceId = preg_match('/^RES-\d{4}-(\d+)$/i', trim($search), $matches) ? (int) $matches[1] : null;
                $query->where(function ($nested) use ($search, $referenceId, $agenceId): void {
                    $nested->where('reference', 'like', "%{$search}%")
                        ->when($referenceId, fn ($inner) => $inner->orWhere('id', $referenceId))
                        ->orWhereHas('client', fn ($client) => $client->where('agence_id', $agenceId)->where('nom', 'like', "%{$search}%"))
                        ->orWhereHas('voiture', function ($voiture) use ($search, $agenceId): void {
                            $voiture->where('agence_id', $agenceId)->where(function ($fields) use ($search): void {
                                $fields->where('marque', 'like', "%{$search}%")
                                    ->orWhere('modele', 'like', "%{$search}%")
                                    ->orWhere('immatriculation', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->when($filters['statut'] ?? null, fn ($query, string $status) => $query->where('statut', $status))
            ->when($filters['date_debut'] ?? null, fn ($query, string $date) => $query->whereDate('date_debut', '>=', $date))
            ->when($filters['date_fin'] ?? null, fn ($query, string $date) => $query->whereDate('date_fin', '<=', $date))
            ->latest('date_debut')
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        $baseQuery = Reservation::query()->where('agence_id', $agenceId);
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'confirmee' => (clone $baseQuery)->where('statut', 'confirmee')->count(),
            'en_cours' => (clone $baseQuery)->where('statut', 'en_cours')->count(),
            'terminee' => (clone $baseQuery)->where('statut', 'terminee')->count(),
        ];

        return view('agence.reservations.index', ['reservations' => $reservations, 'stats' => $stats, 'statuses' => $this->statuses()]);
    }

    public function create(Request $request): View
    {
        return view('agence.reservations.create', $this->formOptions($request, selectedClientId: $request->integer('client_id') ?: null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        [$client, $voiture] = $this->ownedParticipants($request, (int) $data['client_id'], (int) $data['voiture_id']);
        $reservation = DB::transaction(function () use ($request, $data, $client, $voiture): Reservation {
            $voiture = Voiture::query()->lockForUpdate()->findOrFail($voiture->id);
            [$start, $end, $duration, $price, $amount] = $this->pricing($voiture, $data['date_debut'], $data['date_fin']);
            if ($data['statut'] !== 'annulee') {
                $this->ensureVehicleAvailable($request, $voiture, $start, $end);
            }
            $reservation = Reservation::create([
                'agence_id' => $request->user()->agence_id,
                'client_id' => $client->id,
                'voiture_id' => $voiture->id,
                'date_debut' => $start,
                'date_fin' => $end,
                'prix_jour' => $price,
                'montant' => $amount,
                'statut' => $data['statut'],
                'notes' => $data['notes'] ?? null,
            ]);
            $reservation->update(['reference' => sprintf('RES-%d-%04d', $start->year, $reservation->id)]);

            return $reservation;
        });

        return redirect()->route('agence.reservations.show', $reservation)->with('success', 'Réservation créée avec succès.');
    }

    public function show(Request $request, string $reservation): View
    {
        $reservation = $this->ownedReservation($request, $reservation);
        $reservation->load(['client', 'voiture', 'contrats']);

        return view('agence.reservations.show', compact('reservation'));
    }

    public function edit(Request $request, string $reservation): View
    {
        $reservation = $this->ownedReservation($request, $reservation);

        return view('agence.reservations.edit', array_merge(
            ['reservation' => $reservation],
            $this->formOptions($request, $reservation->client_id, $reservation->voiture_id),
        ));
    }

    public function update(Request $request, string $reservation): RedirectResponse
    {
        $reservation = $this->ownedReservation($request, $reservation);
        $data = $this->validatedData($request);
        [$client, $voiture] = $this->ownedParticipants($request, (int) $data['client_id'], (int) $data['voiture_id']);
        DB::transaction(function () use ($request, $reservation, $data, $client, $voiture): void {
            $voiture = Voiture::query()->lockForUpdate()->findOrFail($voiture->id);
            [$start, $end, $duration, $price, $amount] = $this->pricing($voiture, $data['date_debut'], $data['date_fin']);
            if ($data['statut'] !== 'annulee') {
                $this->ensureVehicleAvailable($request, $voiture, $start, $end, $reservation->id);
            }
            $reservation->update([
                'client_id' => $client->id,
                'voiture_id' => $voiture->id,
                'date_debut' => $start,
                'date_fin' => $end,
                'prix_jour' => $price,
                'montant' => $amount,
                'statut' => $data['statut'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('agence.reservations.show', $reservation)->with('success', 'Réservation modifiée avec succès.');
    }

    public function destroy(Request $request, string $reservation): RedirectResponse
    {
        return $this->annuler($request, $reservation);
    }

    public function annuler(Request $request, string $reservation): RedirectResponse
    {
        $reservation = $this->ownedReservation($request, $reservation);
        $reservation->update(['statut' => 'annulee']);

        return redirect()->route('agence.reservations.show', $reservation)->with('success', 'Réservation annulée avec succès.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'integer'],
            'voiture_id' => ['required', 'integer'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'statut' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function formOptions(Request $request, ?int $selectedClientId = null, ?int $selectedVehicleId = null): array
    {
        $agenceId = (int) $request->user()->agence_id;

        return [
            'clients' => Client::query()->where('agence_id', $agenceId)->orderBy('nom')->get(),
            'voitures' => Voiture::query()->where('agence_id', $agenceId)
                ->where(function ($query) use ($selectedVehicleId): void {
                    $query->whereIn('statut', ['disponible', 'loue'])
                        ->when($selectedVehicleId, fn ($inner) => $inner->orWhere('id', $selectedVehicleId));
                })->orderBy('marque')->orderBy('modele')->get(),
            'statuses' => $this->statuses(),
            'selectedClientId' => $selectedClientId,
        ];
    }

    private function ownedParticipants(Request $request, int $clientId, int $voitureId): array
    {
        $agenceId = (int) $request->user()->agence_id;
        $client = Client::withoutGlobalScopes()->find($clientId);
        $voiture = Voiture::withoutGlobalScopes()->find($voitureId);
        abort_unless($client && $voiture && (int) $client->agence_id === $agenceId && (int) $voiture->agence_id === $agenceId, 403);

        return [$client, $voiture];
    }

    private function pricing(Voiture $voiture, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $duration = max(1, (int) $start->diffInDays($end));
        $price = (float) ($voiture->prix_jour ?? 0);
        if ($duration * $price > 99999999.99) {
            throw ValidationException::withMessages(['date_fin' => 'Le montant dépasse la limite autorisée. Réduisez la période de location.']);
        }

        return [$start, $end, $duration, $price, $duration * $price];
    }

    private function ensureVehicleAvailable(Request $request, Voiture $voiture, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreId = null): void
    {
        $conflict = Reservation::withoutGlobalScopes()
            ->where('agence_id', $request->user()->agence_id)
            ->where('voiture_id', $voiture->id)
            ->where('statut', '!=', 'annulee')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['voiture_id' => 'Cette voiture est déjà réservée sur cette période.']);
        }
    }

    private function ownedReservation(Request $request, string|int $id): Reservation
    {
        $reservation = Reservation::withoutGlobalScopes()->find($id);
        abort_if($reservation === null, 404);
        AgencyAccess::reservation($reservation, (int) $request->user()->agence_id);

        return $reservation;
    }

    private function statuses(): array
    {
        return ['confirmee' => 'Confirmée', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
    }
}
