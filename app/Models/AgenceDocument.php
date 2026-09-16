<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgenceDocument extends Model
{
    use BelongsToAgence;

    protected $fillable = [
        'agence_id',
        'nom',
        'type',
        'fichier',
        'taille',
    ];

    protected function casts(): array
    {
        return ['taille' => 'integer'];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }
}
