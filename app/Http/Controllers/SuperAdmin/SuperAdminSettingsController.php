<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class SuperAdminSettingsController extends SettingsController
{
    public function saveSecurity(Request $request)
    {
        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
            'email_verification' => ['required', 'boolean'],
            'session_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'current_password' => ['required', 'current_password'],
        ]);
        unset($data['current_password']);
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        });

        return back()->with('success', 'Paramètres système et sécurité enregistrés.');
    }

    public function storeAdmin(Request $request)
    {
        $data = $request->validate($this->adminRules());
        User::create([
            'name' => $data['admin_name'], 'email' => $data['admin_email'],
            'password' => $data['admin_password'], 'role' => User::ROLE_SUPER_ADMIN,
            'agence_id' => null, 'statut' => 'actif',
        ]);

        return back()->with('success', 'Super Admin ajouté.');
    }

    public function updateAdmin(Request $request, string $admin)
    {
        $target = User::where('role', User::ROLE_SUPER_ADMIN)->whereNull('agence_id')->findOrFail($admin);
        $data = $request->validate($this->adminRules($target));
        abort_if($target->is($request->user()) && $data['admin_status'] !== 'actif', 422, 'Vous ne pouvez pas désactiver votre propre compte.');
        DB::transaction(function () use ($target, $data) {
            $active = User::where('role', User::ROLE_SUPER_ADMIN)->whereNull('agence_id')->where('statut', 'actif')->orderBy('id')->lockForUpdate()->get();
            abort_if($data['admin_status'] === 'inactif' && $active->count() === 1 && $active->first()->is($target), 422, 'Le dernier Super Admin actif doit être conservé.');
            $target->name = $data['admin_name'];
            if ($target->email !== $data['admin_email']) {
                $target->email_verified_at = null;
            }
            $target->email = $data['admin_email'];
            $target->statut = $data['admin_status'];
            if (! empty($data['admin_password'])) {
                $target->password = $data['admin_password'];
                $target->remember_token = null;
            }
            $target->save();
        });

        return back()->with('success', 'Super Admin mis à jour.');
    }

    private function adminRules(?User $target = null): array
    {
        return [
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'admin_password' => [$target ? 'nullable' : 'required', 'string', 'min:12', 'max:72', 'confirmed'],
            'admin_status' => [$target ? 'required' : 'nullable', Rule::in(['actif', 'inactif'])],
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function verificationNotice()
    {
        return view('super-admin.parametres.verify');
    }

    public function sendVerification(Request $request)
    {
        $user = $request->user();
        $url = URL::temporarySignedRoute('super-admin.verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        Mail::raw("Confirmez votre adresse email GLV en ouvrant ce lien (valable 60 minutes) :\n".$url,
            fn ($message) => $message->to($user->email)->subject('GLV — Vérification email'));

        return back()->with('success', 'Lien de vérification envoyé.');
    }

    public function verify(Request $request, string $id, string $hash)
    {
        abort_unless((string) $request->user()->id === $id && hash_equals(sha1($request->user()->email), $hash), 403);
        $request->user()->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('super-admin.settings.edit');
    }
}
