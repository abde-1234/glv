<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('agence.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]));

        return back()->with('success', 'Profil mis à jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('agence.profile.edit', ['section' => 'password'])
            ->with('success', 'Mot de passe modifié avec succès.');
    }
}
