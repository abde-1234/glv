<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgenceUserController extends Controller
{
    public function index(Request $request): View
    {
        $agenceId = $this->agencyId($request);
        $users = User::query()
            ->where('agence_id', $agenceId)
            ->orderByRaw("CASE WHEN role = 'admin_agence' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('agence.settings.users', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $agenceId = $this->agencyId($request);
        $data = $request->validate($this->rules());

        User::create([
            'agence_id' => $agenceId,
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'statut' => 'actif',
        ]);

        return back()->with('success', 'Utilisateur ajouté avec succès.');
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $user = $this->ownedUser($request, $user);
        $data = $request->validate($this->rules($user));

        if ($user->is($request->user()) && ($data['role'] !== User::ROLE_ADMIN_AGENCE || $data['statut'] !== 'actif')) {
            throw ValidationException::withMessages(['statut' => 'Votre propre compte doit rester administrateur et actif.']);
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'role' => $data['role'],
            'statut' => $data['statut'],
        ];

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        $user->update($updates);

        return back()->with('success', 'Utilisateur modifié avec succès.');
    }

    public function destroy(Request $request, string $user): RedirectResponse
    {
        $user = $this->ownedUser($request, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $user->delete();

        return back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN_AGENCE, User::ROLE_EMPLOYE])],
            'statut' => [$user ? 'required' : 'nullable', Rule::in(['actif', 'inactif'])],
        ];
    }

    private function ownedUser(Request $request, string|int $id): User
    {
        $agenceId = $this->agencyId($request);
        $user = User::query()->find($id);
        abort_if($user === null, 404);
        abort_unless((int) $user->agence_id === $agenceId && in_array($user->role, User::AGENCY_ROLES, true), 403);

        return $user;
    }

    private function agencyId(Request $request): int
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN_AGENCE, 403);

        return (int) $request->user()->agence_id;
    }
}
