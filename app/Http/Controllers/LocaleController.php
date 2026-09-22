<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        $previous = $this->localPreviousUrl($request);

        return $previous === null ? redirect()->route('login') : redirect()->to($previous);
    }

    private function localPreviousUrl(Request $request): ?string
    {
        $previous = $request->headers->get('referer');
        if (! is_string($previous)) {
            return null;
        }

        $target = parse_url($previous);
        $base = parse_url(config('app.url'));

        return isset($target['scheme'], $target['host'], $base['scheme'], $base['host'])
            && strtolower($target['scheme']) === strtolower($base['scheme'])
            && strtolower($target['host']) === strtolower($base['host'])
            && ($target['port'] ?? null) === ($base['port'] ?? null)
                ? $previous
                : null;
    }
}
