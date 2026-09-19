<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\SubscriptionNotificationEvent;
use App\Models\User;
use App\Notifications\GlvNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubscriptionNotificationService
{
    public function checkAll(): int
    {
        $created = 0;

        Agence::query()
            ->whereNotNull('date_expiration')
            ->with(['users' => fn ($query) => $query->where('statut', 'actif')])
            ->chunkById(100, function ($agencies) use (&$created): void {
                foreach ($agencies as $agency) {
                    $created += (int) $this->check($agency);
                }
            });

        return $created;
    }

    public function check(Agence $agency): bool
    {
        if ($agency->isSubscriptionSuspended() || $agency->date_expiration === null) {
            return false;
        }

        $days = (int) today()->diffInDays($agency->date_expiration, false);
        $event = match (true) {
            $agency->statut === 'expire' => 'subscription_expired',
            $days < 0 => 'subscription_expired',
            $days === 1 => 'expiration_1_day',
            in_array($days, [30, 15, 7, 3], true) => 'expiration_'.$days.'_days',
            default => null,
        };

        if ($event === null) {
            return false;
        }

        try {
            return DB::transaction(function () use ($agency, $days, $event): bool {
                SubscriptionNotificationEvent::query()->create([
                    'agence_id' => $agency->id,
                    'event_type' => $event,
                    'expiration_date' => $agency->date_expiration,
                ]);

                $copy = $this->copy($event, $days);
                Notification::send($agency->users, new GlvNotification([
                    'type' => $event,
                    'title' => $copy['title'],
                    'message' => $copy['message'],
                    'url' => route('agence.settings.subscription'),
                    'agency_id' => $agency->id,
                ]));

                if (in_array($event, ['expiration_7_days', 'subscription_expired'], true)) {
                    $this->notifySuperAdmins([
                        'type' => $event,
                        'title' => $copy['title'].' — '.$agency->nom,
                        'message' => $copy['message'],
                        'url' => route('super-admin.abonnements.index', ['q' => $agency->nom]),
                        'agency_id' => $agency->id,
                    ]);
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

    }

    public function notifySuperAdmins(array $payload): void
    {
        Notification::send(
            User::query()->where('role', User::ROLE_SUPER_ADMIN)->where('statut', 'actif')->get(),
            new GlvNotification($payload),
        );
    }

    private function copy(string $event, int $days): array
    {
        return match ($event) {
            'expiration_30_days' => ['title' => 'Expiration prochaine', 'message' => 'Votre abonnement arrive bientôt à expiration.'],
            'expiration_15_days' => ['title' => 'Expiration prochaine', 'message' => 'Votre abonnement expire dans 15 jours.'],
            'expiration_7_days' => ['title' => 'Attention', 'message' => 'Attention : votre abonnement expire dans 7 jours.'],
            'expiration_3_days' => ['title' => 'Expiration urgente', 'message' => 'Votre abonnement expire dans 3 jours.'],
            'expiration_1_day' => ['title' => 'Expiration demain', 'message' => 'Votre abonnement expire demain.'],
            default => ['title' => 'Abonnement expiré', 'message' => 'Votre abonnement a expiré. Contactez le Super Admin pour demander son renouvellement.'],
        };
    }
}
