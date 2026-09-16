<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Support\AgencyAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use League\Flysystem\FilesystemException;

class ContractPdfController extends Controller
{
    public function preview(Request $request, string $contrat): Response
    {
        return $this->pdfResponse($request, $contrat, false);
    }

    public function download(Request $request, string $contrat): Response
    {
        return $this->pdfResponse($request, $contrat, true);
    }

    private function pdfResponse(Request $request, string $contractId, bool $download): Response
    {
        $contrat = $this->ownedContract($request, $contractId);
        $dailyPrice = $contrat->reservation?->prix_jour
            ?? ($contrat->montant !== null ? (float) $contrat->montant / $contrat->durationInDays() : null);
        $filename = 'GLV-contrat-'.$this->safeReference($contrat->displayReference()).'.pdf';

        $pdf = Pdf::loadView('agence.contrats.pdf', [
            'contrat' => $contrat,
            'dailyPrice' => $dailyPrice,
            'agencyLogoDataUri' => $this->imageDataUri($contrat->agence->logo),
            'vehiclePhotoDataUri' => $this->imageDataUri($contrat->voiture->photo),
            'generatedAt' => now(),
            'statusLabel' => $this->statusLabel($contrat->statut),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ]);

        $response = $download ? $pdf->download($filename) : $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function ownedContract(Request $request, string|int $id): Contrat
    {
        $contrat = Contrat::withoutGlobalScopes()->find($id);

        abort_if($contrat === null, 404);

        $agencyId = (int) $request->user()->agence_id;
        AgencyAccess::contract($contrat, $agencyId);

        $contrat->load(['agence', 'client', 'voiture', 'reservation']);

        $relationsBelongToAgency = $contrat->agence !== null
            && $contrat->client !== null
            && $contrat->voiture !== null
            && (int) $contrat->agence->getKey() === $agencyId
            && (int) $contrat->client->agence_id === $agencyId
            && (int) $contrat->voiture->agence_id === $agencyId
            && ($contrat->reservation_id === null || ($contrat->reservation !== null &&
                (int) $contrat->reservation->agence_id === $agencyId
                && (int) $contrat->reservation->client_id === (int) $contrat->client_id
                && (int) $contrat->reservation->voiture_id === (int) $contrat->voiture_id
            ));

        abort_unless($relationsBelongToAgency, 403);

        return $contrat;
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path) || $disk->size($path) > 4 * 1024 * 1024) {
                return null;
            }

            $mime = $disk->mimeType($path);

            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }

            $bytes = $disk->get($path);
            if (@getimagesizefromstring($bytes) === false) {
                return null;
            }
            if (! function_exists('imagecreatefromstring')) {
                // JPEG can be embedded without GD; other formats need decoding support.
                return $mime === 'image/jpeg' ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
            }
            // Decode before handing images to DomPDF: corrupt files use the placeholder.
            $decoded = @imagecreatefromstring($bytes);
            if ($decoded === false) {
                return null;
            }
            ob_start();
            imagepng($decoded);
            $png = ob_get_clean();
            imagedestroy($decoded);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (FilesystemException) {
            return null;
        }
    }

    private function safeReference(string $reference): string
    {
        $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference) ?: 'contrat';

        return trim($reference, '-_') ?: 'contrat';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'actif' => 'Actif',
            'termine' => 'Terminé',
            'annule' => 'Annulé',
            default => ucfirst($status),
        };
    }
}
