<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\PlatformSetting;
use App\Models\RenewalRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function general(Request $request): View
    {
        return view('agence.settings.general', ['agence' => $this->currentAgency($request)]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $agence = $this->currentAgency($request);
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldLogo = $agence->logo;
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('agences', 'public');
        }

        $agence->update($data);

        if (isset($data['logo']) && $oldLogo && $oldLogo !== $data['logo']) {
            Storage::disk('public')->delete($oldLogo);
        }

        return back()->with('success', 'Informations de l’agence mises à jour.');
    }

    public function contact(Request $request): View
    {
        return view('agence.settings.contact', ['agence' => $this->currentAgency($request)]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $agence = $this->currentAgency($request);
        $agence->update($request->validate([
            'adresse' => ['required', 'string', 'max:1000'],
            'ville' => ['required', 'string', 'max:100'],
            'code_postal' => ['nullable', 'string', 'max:20'],
            'pays' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'site_web' => ['nullable', 'url:http,https', 'max:255'],
        ]));

        return back()->with('success', 'Coordonnées mises à jour.');
    }

    public function subscription(Request $request): View
    {
        $agence = $this->subscriptionAgency($request);
        $platform = PlatformSetting::current();

        return view('agence.settings.subscription', [
            'agence' => $agence,
            'remainingDays' => $agence->subscriptionDaysRemaining(),
            'displayStatus' => $agence->subscriptionStatus(),
            'periodRemainingPercentage' => $agence->subscriptionPeriodRemainingPercentage(),
            'supportEmail' => $platform->support_email,
            'supportPhone' => $platform->support_phone,
            'canManageAgency' => $request->user()->role === User::ROLE_ADMIN_AGENCE,
            'pendingRenewal' => $agence->renewalRequests()->with('requester')->where('status', RenewalRequest::PENDING)->latest()->first(),
            'renewalHistory' => $agence->renewalRequests()->with(['requester', 'processor'])->latest()->limit(5)->get(),
        ]);
    }

    public function preferences(Request $request): View
    {
        return view('agence.settings.preferences', ['agence' => $this->currentAgency($request)]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $agence = $this->currentAgency($request);
        $data = $request->validate([
            'devise' => ['required', Rule::in(['MAD', 'EUR', 'USD'])],
            'format_date' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'd-m-Y'])],
            'langue' => ['required', Rule::in(['fr', 'en', 'ar'])],
            'elements_par_page' => ['required', 'integer', Rule::in([10, 25, 50])],
            'notifications_email' => ['nullable', 'boolean'],
            'rappel_reservation' => ['nullable', 'boolean'],
            'rapport_mensuel' => ['nullable', 'boolean'],
        ]);

        foreach (['notifications_email', 'rappel_reservation', 'rapport_mensuel'] as $preference) {
            $data[$preference] = $request->boolean($preference);
        }

        $agence->update($data);

        return back()->with('success', 'Préférences enregistrées.');
    }

    private function currentAgency(Request $request): Agence
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN_AGENCE, 403);

        return Agence::query()->findOrFail((int) $request->user()->agence_id);
    }

    private function subscriptionAgency(Request $request): Agence
    {
        abort_unless(in_array($request->user()?->role, User::AGENCY_ROLES, true), 403);

        return Agence::query()->findOrFail((int) $request->user()->agence_id);
    }
}
