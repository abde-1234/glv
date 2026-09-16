<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'agence_id',
        'client_id',
        'voiture_id',
        'reservation_id',
        'reference',
        'date_debut',
        'date_fin',
        'montant',
        'notes',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'montant' => 'decimal:2',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function voiture(): BelongsTo
    {
        return $this->belongsTo(Voiture::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function displayReference(): string
    {
        return $this->reference ?: sprintf('CTR-%s-%04d', $this->date_debut?->format('Y') ?? $this->created_at?->format('Y') ?? now()->year, $this->getKey());
    }

    public function durationInDays(): int
    {
        return max(1, (int) $this->date_debut->diffInDays($this->date_fin));
    }
}
