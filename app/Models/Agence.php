<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agence extends Model
{
    protected $fillable = [
        'nom',
        'description',
        'email',
        'telephone',
        'site_web',
        'ville',
        'code_postal',
        'pays',
        'adresse',
        'logo',
        'statut',
        'type_abonnement',
        'date_debut_abonnement',
        'date_expiration',
        'montant_abonnement',
        'devise',
        'format_date',
        'langue',
        'elements_par_page',
        'notifications_email',
        'rappel_reservation',
        'rapport_mensuel',
    ];

    protected function casts(): array
    {
        return [
            'date_debut_abonnement' => 'date',
            'date_expiration' => 'date',
            'montant_abonnement' => 'decimal:2',
            'elements_par_page' => 'integer',
            'notifications_email' => 'boolean',
            'rappel_reservation' => 'boolean',
            'rapport_mensuel' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function primaryAdmin(): HasOne
    {
        return $this->hasOne(User::class)
            ->where('role', User::ROLE_ADMIN_AGENCE)
            ->oldestOfMany();
    }

    public function voitures(): HasMany
    {
        return $this->hasMany(Voiture::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AgenceDocument::class);
    }

    public function renewalRequests(): HasMany
    {
        return $this->hasMany(RenewalRequest::class);
    }

    public function passwordResetRequests(): HasMany
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    public function subscriptionDaysRemaining(): ?int
    {
        if ($this->date_expiration === null) {
            return null;
        }

        return max(0, (int) today()->diffInDays($this->date_expiration, false));
    }

    public function isSubscriptionSuspended(): bool
    {
        return $this->statut === 'suspendu';
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->statut === 'expire'
            || ($this->date_expiration !== null && $this->date_expiration->lt(today()));
    }

    public function isSubscriptionActive(): bool
    {
        return $this->statut === 'actif'
            && ! $this->isSubscriptionSuspended()
            && ! $this->isSubscriptionExpired();
    }

    public function isSubscriptionTrial(): bool
    {
        return $this->statut === 'essai'
            && $this->date_expiration !== null
            && ! $this->isSubscriptionExpired();
    }

    public function hasValidSubscription(): bool
    {
        return $this->isSubscriptionActive() || $this->isSubscriptionTrial();
    }

    public function isSubscriptionExpiringSoon(): bool
    {
        $days = $this->subscriptionDaysRemaining();

        return $this->isSubscriptionActive()
            && $days !== null
            && $days > 0
            && $days <= 30;
    }

    public function isSubscriptionUrgent(): bool
    {
        $days = $this->subscriptionDaysRemaining();

        return $this->isSubscriptionActive()
            && $days !== null
            && $days > 0
            && $days <= 7;
    }

    public function subscriptionStatus(): string
    {
        if ($this->isSubscriptionSuspended()) {
            return 'suspendu';
        }

        if ($this->isSubscriptionExpired()) {
            return 'expire';
        }

        return $this->statut;
    }

    public function subscriptionPeriodRemainingPercentage(): ?int
    {
        if ($this->date_debut_abonnement === null || $this->date_expiration === null) {
            return null;
        }

        $duration = (int) $this->date_debut_abonnement->diffInDays($this->date_expiration, false);

        if ($duration <= 0) {
            return $this->isSubscriptionExpired() ? 0 : 100;
        }

        $remaining = (int) today()->diffInDays($this->date_expiration, false);

        return (int) round((min($duration, max(0, $remaining)) / $duration) * 100);
    }

    public function scopeWithSubscriptionStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'actif' => $query
                ->where('statut', 'actif')
                ->where(fn (Builder $query) => $query
                    ->whereNull('date_expiration')
                    ->orWhereDate('date_expiration', '>=', today())),
            'essai' => $query
                ->where('statut', 'essai')
                ->whereNotNull('date_expiration')
                ->whereDate('date_expiration', '>=', today()),
            'suspendu' => $query->where('statut', 'suspendu'),
            'expire' => $query->where(fn (Builder $query) => $query
                ->where('statut', 'expire')
                ->orWhere(fn (Builder $query) => $query
                    ->where('statut', '!=', 'suspendu')
                    ->whereNotNull('date_expiration')
                    ->whereDate('date_expiration', '<', today()))),
            default => $query,
        };
    }
}
