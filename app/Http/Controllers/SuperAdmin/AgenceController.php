<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgenceController extends Controller
{
    public function index(Request $request): View
    {
        $agences = Agence::query()
            ->with('primaryAdmin')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim($request->string('q')->toString());

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('nom', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%")
                        ->orWhere('ville', 'like', "%{$search}%")
                        ->orWhereHas('users', fn ($users) => $users
                            ->where('role', User::ROLE_ADMIN_AGENCE)
                            ->where(function ($users) use ($search): void {
                                $users
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('telephone', 'like', "%{$search}%");
                            }));
                });
            })
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->when($request->filled('ville'), fn ($query) => $query->where('ville', $request->string('ville')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.agences.index', [
            'agences' => $agences,
            'totalAgencies' => Agence::count(),
            'activeAgencies' => Agence::where('statut', 'actif')->count(),
            'trialAgencies' => Agence::where('statut', 'essai')->count(),
            'suspendedAgencies' => Agence::where('statut', 'suspendu')->count(),
            'cities' => Agence::query()->whereNotNull('ville')->distinct()->orderBy('ville')->pluck('ville'),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.agences.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $logo = $request->file('logo')?->store('agences/logos', 'public');

        $agence = DB::transaction(function () use ($validated, $logo): Agence {
            $agence = Agence::create([
                ...$this->agenceData($validated),
                'logo' => $logo,
            ]);

            User::create([
                'agence_id' => $agence->id,
                'name' => $validated['gerant_name'],
                'email' => $validated['gerant_email'],
                'telephone' => $validated['gerant_telephone'],
                'password' => Hash::make($validated['gerant_password']),
                'role' => User::ROLE_ADMIN_AGENCE,
            ]);

            return $agence;
        });

        return redirect()
            ->route('super-admin.agences.show', $agence)
            ->with('success', 'Agence créée avec succès.');
    }

    public function show(Agence $agence): View
    {
        $agence->load(['primaryAdmin', 'voitures', 'clients', 'reservations']);

        return view('super-admin.agences.show', compact('agence'));
    }

    public function edit(Agence $agence): View
    {
        $agence->load('primaryAdmin');

        return view('super-admin.agences.edit', compact('agence'));
    }

    public function update(Request $request, Agence $agence): RedirectResponse
    {
        $gerant = $agence->primaryAdmin;
        abort_if($gerant === null, 404, 'Aucun gérant principal trouvé pour cette agence.');

        $validated = $request->validate($this->rules($gerant));
        $previousLogo = $agence->logo;
        $logo = $request->file('logo')?->store('agences/logos', 'public');

        DB::transaction(function () use ($agence, $gerant, $validated, $logo): void {
            $agencyData = $this->agenceData($validated);

            if ($logo !== null) {
                $agencyData['logo'] = $logo;
            }

            $agence->update($agencyData);

            $managerData = [
                'name' => $validated['gerant_name'],
                'email' => $validated['gerant_email'],
                'telephone' => $validated['gerant_telephone'],
            ];

            if (! empty($validated['gerant_password'])) {
                $managerData['password'] = Hash::make($validated['gerant_password']);
            }

            $gerant->update($managerData);
        });

        if ($logo && $previousLogo && preg_match('~\Aagences/(?:logos/)?[A-Za-z0-9_-]+\.(?:png|jpe?g|webp)\z~i', $previousLogo)) {
            Storage::disk('public')->delete($previousLogo);
        }

        return redirect()
            ->route('super-admin.agences.show', $agence)
            ->with('success', 'Agence modifiée avec succès.');
    }

    public function destroy(Agence $agence): RedirectResponse
    {
        DB::transaction(function () use ($agence): void {
            $agence->users()->delete();
            $agence->delete();
        });

        return redirect()
            ->route('super-admin.agences.index')
            ->with('success', 'Agence supprimée avec succès.');
    }

    public function suspendre(Agence $agence): RedirectResponse
    {
        $agence->update(['statut' => 'suspendu']);

        return back()->with('success', 'Agence suspendue avec succès.');
    }

    public function activer(Agence $agence): RedirectResponse
    {
        $agence->update(['statut' => 'actif']);

        return back()->with('success', 'Agence activée avec succès.');
    }

    private function rules(?User $gerant = null): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:30'],
            'ville' => ['required', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'statut' => ['required', Rule::in(['actif', 'essai', 'suspendu', 'expire'])],
            'type_abonnement' => ['nullable', 'string', 'max:100'],
            'date_expiration' => ['nullable', 'date'],
            'gerant_name' => ['required', 'string', 'max:255'],
            'gerant_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($gerant),
            ],
            'gerant_telephone' => ['required', 'string', 'max:30'],
            'gerant_password' => [$gerant ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
        ];
    }

    private function agenceData(array $validated): array
    {
        return [
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'ville' => $validated['ville'],
            'adresse' => $validated['adresse'] ?? null,
            'statut' => $validated['statut'],
            'type_abonnement' => $validated['type_abonnement'] ?? null,
            'date_expiration' => $validated['date_expiration'] ?? null,
        ];
    }
}
