<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Voiture;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VoitureController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::in(array_keys($this->statuses()))],
            'categorie' => ['nullable', 'string', 'max:100'],
        ]);

        $voitures = Voiture::query()
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('marque', 'like', "%{$search}%")
                        ->orWhere('modele', 'like', "%{$search}%")
                        ->orWhere('immatriculation', 'like', "%{$search}%");
                });
            })
            ->when($filters['statut'] ?? null, fn ($query, string $status) => $query->where('statut', $status))
            ->when($filters['categorie'] ?? null, fn ($query, string $category) => $query->where('categorie', $category))
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        $stats = [
            'total' => Voiture::count(),
            'disponible' => Voiture::where('statut', 'disponible')->count(),
            'loue' => Voiture::where('statut', 'loue')->count(),
            'maintenance' => Voiture::where('statut', 'maintenance')->count(),
        ];

        $categories = Voiture::query()
            ->whereNotNull('categorie')
            ->where('categorie', '!=', '')
            ->distinct()
            ->orderBy('categorie')
            ->pluck('categorie');

        return view('agence.voitures.index', [
            'voitures' => $voitures,
            'stats' => $stats,
            'categories' => $categories,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return view('agence.voitures.create', ['statuses' => $this->statuses()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['agence_id'] = $request->user()->agence_id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('voitures', 'public');
        }

        $voiture = Voiture::create($data);

        return redirect()
            ->route('agence.voitures.show', $voiture)
            ->with('success', 'La voiture a été ajoutée avec succès.');
    }

    public function show(Request $request, Voiture $voiture): View
    {
        $this->ensureOwnedByCurrentAgence($request, $voiture);

        return view('agence.voitures.show', [
            'voiture' => $voiture,
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(Request $request, Voiture $voiture): View
    {
        $this->ensureOwnedByCurrentAgence($request, $voiture);

        return view('agence.voitures.edit', [
            'voiture' => $voiture,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, Voiture $voiture): RedirectResponse
    {
        $this->ensureOwnedByCurrentAgence($request, $voiture);
        $data = $this->validatedData($request, $voiture);

        if ($request->hasFile('photo')) {
            $oldPhoto = $voiture->photo;
            $data['photo'] = $request->file('photo')->store('voitures', 'public');
            $voiture->update($data);

            if ($oldPhoto) {
                Storage::disk('public')->delete($oldPhoto);
            }
        } else {
            $voiture->update($data);
        }

        return redirect()
            ->route('agence.voitures.show', $voiture)
            ->with('success', 'Les informations de la voiture ont été mises à jour.');
    }

    public function destroy(Request $request, Voiture $voiture): RedirectResponse
    {
        $this->ensureOwnedByCurrentAgence($request, $voiture);
        $photo = $voiture->photo;
        $voiture->delete();

        if ($photo) {
            Storage::disk('public')->delete($photo);
        }

        return redirect()
            ->route('agence.voitures.index')
            ->with('success', 'La voiture a été supprimée.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Voiture $voiture = null): array
    {
        $agenceId = (int) $request->user()->agence_id;
        $request->validate(['immatriculation' => ['required', 'string', 'max:50']]);
        $request->merge([
            'immatriculation' => mb_strtoupper(trim((string) $request->input('immatriculation'))),
        ]);

        $uniquePlate = Rule::unique('voitures', 'immatriculation')
            ->where(fn ($query) => $query->where('agence_id', $agenceId));

        if ($voiture) {
            $uniquePlate->ignore($voiture->getKey());
        }

        $data = $request->validate([
            'marque' => ['required', 'string', 'max:100'],
            'modele' => ['required', 'string', 'max:100'],
            'immatriculation' => ['required', 'string', 'max:50', $uniquePlate],
            'categorie' => ['nullable', 'string', 'max:100'],
            'annee' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'prix_jour' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'kilometrage' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'statut' => ['required', Rule::in(array_keys($this->statuses()))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return $data;
    }

    private function ensureOwnedByCurrentAgence(Request $request, Voiture $voiture): void
    {
        abort_unless((int) $voiture->agence_id === (int) $request->user()->agence_id, 404);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        return [
            'disponible' => 'Disponible',
            'loue' => 'Louée',
            'maintenance' => 'Maintenance',
            'indisponible' => 'Indisponible',
        ];
    }
}
