<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToAgence
{
    protected static function bootBelongsToAgence(): void
    {
        static::addGlobalScope('agence', function (Builder $builder): void {
            $user = Auth::user();

            if (! in_array($user?->role, User::AGENCY_ROLES, true)) {
                return;
            }

            if ($user->agence_id === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('agence_id'), $user->agence_id);
        });

        static::creating(function (Model $model): void {
            $user = Auth::user();

            if (in_array($user?->role, User::AGENCY_ROLES, true)) {
                $model->setAttribute('agence_id', $user->agence_id);
            }
        });
    }

    public function scopeForAgence(Builder $query, int $agenceId): Builder
    {
        return $query->where($query->qualifyColumn('agence_id'), $agenceId);
    }
}
