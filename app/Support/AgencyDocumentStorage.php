<?php

namespace App\Support;

use App\Models\AgenceDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AgencyDocumentStorage
{
    public static function path(AgenceDocument $document): string
    {
        $prefix = 'agences/'.$document->agence_id.'/documents/';
        $path = $document->fichier;
        abort_unless(is_string($path) && preg_match(
            '~\A'.preg_quote($prefix, '~').'[A-Za-z0-9_-]+\.(pdf|jpe?g|png)\z~i', $path,
        ), 403);

        return $path;
    }

    /** Keep the relative path and DB record; verify the private copy before removing public access. */
    public static function privatize(AgenceDocument $document): void
    {
        $path = self::path($document);
        $public = Storage::disk('public');
        $private = Storage::disk('documents');
        if (! $public->exists($path)) {
            return;
        }

        $contents = $public->get($path);
        if ($private->exists($path)) {
            if (! hash_equals(hash('sha256', $contents), hash('sha256', $private->get($path)))) {
                throw new RuntimeException('La copie privée diffère du document public.');
            }
        } elseif (! $private->put($path, $contents)) {
            throw new RuntimeException('Le document ne peut pas être stocké en privé.');
        }

        if (! hash_equals(hash('sha256', $contents), hash('sha256', $private->get($path)))) {
            throw new RuntimeException('La vérification du document privé a échoué.');
        }
        if (! $public->delete($path)) {
            throw new RuntimeException('La copie publique ne peut pas être retirée.');
        }
    }
}
