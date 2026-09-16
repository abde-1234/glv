<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voiture extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'agence_id',
        'marque',
        'modele',
        'immatriculation',
        'categorie',
        'annee',
        'prix_jour',
        'kilometrage',
        'statut',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'prix_jour' => 'decimal:2',
            'kilometrage' => 'integer',
        ];
    }

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
