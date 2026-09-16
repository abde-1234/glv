<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAgenceSubscription
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $agence = $request->user()?->agence;

        abort_if($agence === null, 403);

        if (! $agence->hasValidSubscription()) {
            return redirect()
                ->route('agence.settings.subscription')
                ->with('warning', 'Votre abonnement doit être actif pour accéder à cette fonctionnalité.');
        }

        return $next($request);
    }
}
