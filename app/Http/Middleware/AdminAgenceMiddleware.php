<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAgenceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            in_array($user?->role, User::AGENCY_ROLES, true)
                && $user->statut === 'actif'
                && $user->agence_id !== null,
            403,
        );

        $agence = $user->agence;

        abort_if($agence === null, 403);

        return $next($request);
    }
}
