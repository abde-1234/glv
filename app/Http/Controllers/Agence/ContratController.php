<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Reservation;
use App\Support\AgencyAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContratController extends Controller
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

        $contrats = Contrat::query()
            ->where('agence_id', $agenceId)
            ->with(['client', 'voiture', 'reservation'])
            ->when($filters['q'] ?? null, function ($query, string $search) use ($agenceId): void {
                $query->where(function ($nested) use ($search, $agenceId): void {
                    $nested->where('reference', 'like', "%{$search}%")
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

        $baseQuery = Contrat::query()->where('agence_id', $agenceId);
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'actif' => (clone $baseQuery)->where('statut', 'actif')->count(),
            'termine' => (clone $baseQuery)->where('statut', 'termine')->count(),
            'annule' => (clone $baseQuery)->where('statut', 'annule')->count(),
        ];

        return view('agence.contrats.index', ['contrats' => $contrats, 'stats' => $stats, 'statuses' => $this->statuses()]);
    }

    public function create(Request $request): View
    {
        return view('agence.contrats.create', $this->formOptions($request, $request->integer('reservation_id') ?: null));
    }

    public function createFromReservation(Request $request, string $reservation): View|RedirectResponse
    {
        $reservation = $this->ownedReservation($request, $reservation);
        $existing = Contrat::query()
            ->where('agence_id', $request->user()->agence_id)
            ->where('reservation_id', $reservation->id)
            ->first();

        if ($existing) {
            return redirect()->route('agence.contrats.show', $existing)->with('success', 'Un contrat existe déjà pour cette réservation.');
        }

        return view('agence.contrats.create', $this->formOptions($request, $reservation->id));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reservation_id' => ['required', 'integer'],
            'statut' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $reservation = $this->ownedReservation($request, (string) $data['reservation_id']);

        $contrat = DB::transaction(function () use ($request, $data, $reservation): Contrat {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            AgencyAccess::reservation($reservation, (int) $request->user()->agence_id);
            if ($reservation->statut === 'annulee') {
                throw ValidationException::withMessages(['reservation_id' => 'Une réservation annulée ne peut pas être contractualisée.']);
            }
            if (Contrat::query()->where('agence_id', $request->user()->agence_id)->where('reservation_id', $reservation->id)->exists()) {
                throw ValidationException::withMessages(['reservation_id' => 'Un contrat existe déjà pour cette réservation.']);
            }
            $contrat = Contrat::create([
                'agence_id' => $request->user()->agence_id,
                'reservation_id' => $reservation->id,
                'client_id' => $reservation->client_id,
                'voiture_id' => $reservation->voiture_id,
                'reference' => 'PENDING-'.Str::uuid(),
                'date_debut' => $reservation->date_debut,
                'date_fin' => $reservation->date_fin,
                'montant' => $reservation->montant,
                'statut' => $data['statut'],
                'notes' => $data['notes'] ?? null,
            ]);
            $contrat->update(['reference' => sprintf('CTR-%d-%04d', $reservation->date_debut->year, $contrat->id)]);

            return $contrat;
        });

        return redirect()->route('agence.contrats.show', $contrat)->with('success', 'Contrat créé avec succès.');
    }

    public function show(Request $request, string $contrat): View
    {
        $contrat = $this->ownedContract($request, $contrat);
        $contrat->load(['client', 'voiture', 'reservation']);

        return view('agence.contrats.show', compact('contrat'));
    }

    public function edit(Request $request, string $contrat): View
    {
        return view('agence.contrats.edit', ['contrat' => $this->ownedContract($request, $contrat), 'statuses' => $this->statuses()]);
    }

    public function update(Request $request, string $contrat): RedirectResponse
    {
        $contrat = $this->ownedContract($request, $contrat);
        $data = $request->validate([
            'statut' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $contrat->update($data);

        return redirect()->route('agence.contrats.show', $contrat)->with('success', 'Contrat modifié avec succès.');
    }

    public function destroy(Request $request, string $contrat): RedirectResponse
    {
        return $this->resilier($request, $contrat);
    }

    public function resilier(Request $request, string $contrat): RedirectResponse
    {
        $contrat = $this->ownedContract($request, $contrat);
        $contrat->update(['statut' => 'annule']);

        return redirect()->route('agence.contrats.show', $contrat)->with('success', 'Contrat résilié avec succès.');
    }

    private function formOptions(Request $request, ?int $selectedReservationId = null): array
    {
        $agenceId = (int) $request->user()->agence_id;
        $reservations = Reservation::query()
            ->where('agence_id', $agenceId)
            ->where('statut', '!=', 'annulee')
            ->whereDoesntHave('contrats')
            ->with(['client', 'voiture'])
            ->latest('date_debut')
            ->get();

        return ['reservations' => $reservations, 'selectedReservationId' => $selectedReservationId, 'statuses' => $this->statuses()];
    }

    private function ownedReservation(Request $request, string|int $id): Reservation
    {
        $reservation = Reservation::withoutGlobalScopes()->find($id);
        abort_if($reservation === null, 404);
        AgencyAccess::reservation($reservation, (int) $request->user()->agence_id);

        return $reservation;
    }

    private function ownedContract(Request $request, string|int $id): Contrat
    {
        $contrat = Contrat::withoutGlobalScopes()->find($id);
        abort_if($contrat === null, 404);
        AgencyAccess::contract($contrat, (int) $request->user()->agence_id);

        return $contrat;
    }

    private function statuses(): array
    {
        return ['actif' => 'Actif', 'termine' => 'Terminé', 'annule' => 'Annulé'];
    }
}
