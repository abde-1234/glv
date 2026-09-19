<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $user = $this->authorizedUser($request);
        $item = $user->notifications()->findOrFail($notification);
        $item->markAsRead();
        $url = $item->data['url'] ?? null;

        return is_string($url) && $this->isLocalUrl($url)
            ? redirect()->to($url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->authorizedUser($request)->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();
        $hasValidScope = ($user?->role === User::ROLE_SUPER_ADMIN && $user->agence_id === null)
            || (in_array($user?->role, User::AGENCY_ROLES, true) && $user->agence_id !== null);

        abort_unless($user?->statut === 'actif' && $hasValidScope, 403);

        return $user;
    }

    private function isLocalUrl(string $url): bool
    {
        $target = parse_url($url);
        $base = parse_url(config('app.url'));

        return isset($target['scheme'], $target['host'], $base['scheme'], $base['host'])
            && strtolower($target['scheme']) === strtolower($base['scheme'])
            && strtolower($target['host']) === strtolower($base['host'])
            && ($target['port'] ?? null) === ($base['port'] ?? null);
    }
}
