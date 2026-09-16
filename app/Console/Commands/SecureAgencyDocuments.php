<?php

namespace App\Console\Commands;

use App\Models\AgenceDocument;
use App\Support\AgencyDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SecureAgencyDocuments extends Command
{
    protected $signature = 'glv:secure-documents {--dry-run : Vérifier sans déplacer les fichiers}';

    protected $description = 'Déplacer les anciens documents vers le stockage privé, après vérification de leur copie';

    public function handle(): int
    {
        $count = 0;
        $errors = 0;
        foreach (AgenceDocument::withoutGlobalScopes()->cursor() as $document) {
            try {
                $path = AgencyDocumentStorage::path($document);
                if (! Storage::disk('public')->exists($path)) {
                    if (! Storage::disk('documents')->exists($path)) {
                        $this->warn('Document #'.$document->id.' : fichier manquant.');
                        $errors++;
                    }

                    continue;
                }
                if (! $this->option('dry-run')) {
                    AgencyDocumentStorage::privatize($document);
                }
                $count++;
            } catch (Throwable $exception) {
                $errors++;
                $this->error('Document #'.$document->id.' : transfert refusé. Vérifiez son chemin et les permissions.');
                report($exception);
            }
        }
        $this->info($count.' document(s) '.($this->option('dry-run') ? 'à protéger' : 'protégé(s)').', '.$errors.' anomalie(s).');

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
