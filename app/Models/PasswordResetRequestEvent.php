<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetRequestEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_CREATED = 'created';

    public const TYPE_APPROVED = 'approved';

    public const TYPE_REJECTED = 'rejected';

    public const TYPE_EXPIRED = 'expired';

    public const TYPE_RESET_USED = 'reset_used';

    public const TYPE_PASSWORD_CHANGED = 'password_changed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function resetRequest(): BelongsTo
    {
        return $this->belongsTo(PasswordResetRequest::class, 'password_reset_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
