<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('super-admin.parametres.index', [
            'settings' => PlatformSetting::current(),
            'system' => Setting::values(),
            'admins' => User::where('role', User::ROLE_SUPER_ADMIN)->whereNull('agence_id')->orderBy('name')->paginate(10),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['section' => ['required', Rule::in(['platform', 'preferences', 'account', 'password'])]]);

        match ($request->input('section')) {
            'platform' => $this->updatePlatform($request),
            'preferences' => $this->updatePreferences($request),
            'account' => $this->updateAccount($request),
            'password' => $this->updatePassword($request),
        };

        return redirect()->route('super-admin.settings.edit')
            ->with('success', $request->input('section') === 'password' ? 'Mot de passe modifié avec succès.' : 'Paramètres enregistrés avec succès.');
    }

    private function updatePlatform(Request $request): void
    {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $settings = PlatformSetting::current();
        $previousLogo = $settings->logo;
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('platform/logos', 'public');

            if (! is_string($logo) || $logo === '') {
                throw ValidationException::withMessages([
                    'logo' => 'Le logo n’a pas pu être enregistré. Veuillez réessayer.',
                ]);
            }

            $data['logo'] = $logo;
        }

        try {
            $settings->fill($data)->save();
        } catch (Throwable $exception) {
            if (isset($data['logo'])) {
                Storage::disk('public')->delete($data['logo']);
            }
            throw $exception;
        }

        if (isset($data['logo']) && $previousLogo && str_starts_with($previousLogo, 'platform/logos/')) {
            Storage::disk('public')->delete($previousLogo);
        }
    }

    private function updatePreferences(Request $request): void
    {
        PlatformSetting::current()->fill($request->validate([
            'default_language' => ['required', Rule::in(['fr', 'en', 'ar'])],
            'default_currency' => ['required', Rule::in(['MAD', 'EUR', 'USD'])],
            'date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'm/d/Y'])],
            'per_page' => ['required', 'integer', Rule::in([10, 25, 50])],
        ]))->save();
    }

    private function updateAccount(Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user())],
        ]);
        if ($request->user()->email !== $data['email']) {
            $request->user()->email_verified_at = null;
        }
        $request->user()->update($data);
    }

    private function updatePassword(Request $request): void
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);
    }
}
