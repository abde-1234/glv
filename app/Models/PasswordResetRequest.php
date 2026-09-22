<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PasswordResetRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'agence_id', 'user_id', 'processed_by', 'status', 'pending_key',
        'requested_at', 'processed_at', 'expires_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PasswordResetRequestEvent::class)->oldest('created_at')->oldest('id');
    }

    public function recordEvent(string $eventType, ?User $actor = null, ?array $metadata = null): PasswordResetRequestEvent
    {
        $event = new PasswordResetRequestEvent;
        $event->forceFill([
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'metadata' => $metadata,
        ]);

        $this->events()->save($event);

        return $event;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function expireIfNeeded(?User $actor = null): bool
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true)
            || $this->expires_at === null
            || $this->expires_at->isFuture()) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
            'pending_key' => null,
            'processed_at' => $this->processed_at ?? now(),
        ])->save();

        $this->recordEvent(PasswordResetRequestEvent::TYPE_EXPIRED, $actor);

        return true;
    }
}
