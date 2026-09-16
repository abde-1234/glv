<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->role === User::ROLE_SUPER_ADMIN
            && $user->statut === 'actif'
            && $user->agence_id === null, 403);

        $settings = Setting::values();
        $last = $request->session()->get('super_admin_last_activity');
        if ($last && time() - $last > (int) $settings['session_minutes'] * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Session expirée. Veuillez vous reconnecter.']);
        }
        $request->session()->put('super_admin_last_activity', time());
        if ($settings['email_verification'] === '1' && ! $user->email_verified_at
            && ! $request->routeIs('super-admin.verification.*')) {
            return redirect()->route('super-admin.verification.notice');
        }

        return $next($request);
    }
}
