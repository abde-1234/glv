<?php

namespace App\Support;

use App\Models\Contrat;
use App\Models\Reservation;

/** Explicit tenant checks, also for legacy records with inconsistent foreign keys. */
final class AgencyAccess
{
    public static function reservation(Reservation $reservation, int $agencyId): void
    {
        abort_unless((int) $reservation->agence_id === $agencyId, 403);
        self::participants($reservation, $agencyId);
    }

    public static function contract(Contrat $contract, int $agencyId): void
    {
        abort_unless((int) $contract->agence_id === $agencyId, 403);
        self::participants($contract, $agencyId);

        if ($contract->reservation_id !== null) {
            $reservation = $contract->reservation()->withoutGlobalScopes()->first();
            abort_if($reservation === null, 403);
            self::reservation($reservation, $agencyId);
        }
    }

    private static function participants(Reservation|Contrat $record, int $agencyId): void
    {
        abort_unless(
            $record->client()->withoutGlobalScopes()->where('agence_id', $agencyId)->exists()
            && $record->voiture()->withoutGlobalScopes()->where('agence_id', $agencyId)->exists(),
            403,
        );
    }
}
