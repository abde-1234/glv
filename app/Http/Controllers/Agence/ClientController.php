<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $agenceId = (int) $request->user()->agence_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::in(['actif', 'inactif'])],
        ]);

        $clients = Client::query()
            ->where('agence_id', $agenceId)
            ->withCount(['reservations' => fn ($query) => $query->where('agence_id', $agenceId)])
            ->withMax(['reservations' => fn ($query) => $query->where('agence_id', $agenceId)], 'date_debut')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('nom', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%")
                        ->orWhere('cin', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['ville'] ?? null, fn ($query, string $ville) => $query->where('ville', $ville))
            ->when($filters['statut'] ?? null, fn ($query, string $statut) => $query->where('statut', $statut))
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        $baseQuery = Client::query()->where('agence_id', $agenceId);
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'new' => (clone $baseQuery)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'active' => (clone $baseQuery)->where('statut', 'actif')->count(),
            'loyal' => (clone $baseQuery)->whereHas(
                'reservations',
                fn ($query) => $query->where('agence_id', $agenceId),
                '>=',
                2,
            )->count(),
        ];

        $villes = Client::query()
            ->where('agence_id', $agenceId)
            ->whereNotNull('ville')
            ->where('ville', '!=', '')
            ->distinct()
            ->orderBy('ville')
            ->pluck('ville');

        return view('agence.clients.index', compact('clients', 'stats', 'villes'));
    }

    public function create(): View
    {
        return view('agence.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['agence_id'] = $request->user()->agence_id;
        $client = Client::create($data);

        return redirect()->route('agence.clients.show', $client)->with('success', 'Client ajouté avec succès.');
    }

    public function show(Request $request, string $client): View
    {
        $client = $this->ownedClient($request, $client);
        $recentReservations = $client->reservations()
            ->where('agence_id', $request->user()->agence_id)
            ->with('voiture')
            ->latest('date_debut')
            ->limit(5)
            ->get();

        return view('agence.clients.show', compact('client', 'recentReservations'));
    }

    public function edit(Request $request, string $client): View
    {
        return view('agence.clients.edit', ['client' => $this->ownedClient($request, $client)]);
    }

    public function update(Request $request, string $client): RedirectResponse
    {
        $client = $this->ownedClient($request, $client);
        $client->update($this->validatedData($request));

        return redirect()->route('agence.clients.show', $client)->with('success', 'Client modifié avec succès.');
    }

    public function destroy(Request $request, string $client): RedirectResponse
    {
        $client = $this->ownedClient($request, $client);
        $client->delete();

        return redirect()->route('agence.clients.index')->with('success', 'Client supprimé avec succès.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'max:30'],
            'cin' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'statut' => ['required', Rule::in(['actif', 'inactif'])],
        ]);
    }

    private function ownedClient(Request $request, string|int $id): Client
    {
        $client = Client::withoutGlobalScopes()->find($id);
        abort_if($client === null, 404);
        abort_unless((int) $client->agence_id === (int) $request->user()->agence_id, 403);

        return $client;
    }
}
