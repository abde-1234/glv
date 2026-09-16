<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'agence_id',
        'nom',
        'telephone',
        'email',
        'cin',
        'ville',
        'adresse',
        'notes',
        'statut',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }
}
