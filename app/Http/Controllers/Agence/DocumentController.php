<?php

namespace App\Http\Controllers\Agence;

use App\Http\Controllers\Controller;
use App\Models\AgenceDocument;
use App\Models\User;
use App\Support\AgencyDocumentStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $agenceId = $this->agencyId($request);
        $documents = AgenceDocument::query()
            ->where('agence_id', $agenceId)
            ->latest()
            ->get();

        return view('agence.settings.documents', ['documents' => $documents, 'documentTypes' => $this->types()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agenceId = $this->agencyId($request);
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'fichier' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ]);
        $file = $request->file('fichier');
        $path = $file->store("agences/{$agenceId}/documents", 'documents');

        AgenceDocument::create([
            'agence_id' => $agenceId,
            'nom' => $data['nom'],
            'type' => $data['type'],
            'fichier' => $path,
            'taille' => $file->getSize(),
        ]);

        return back()->with('success', 'Document ajouté avec succès.');
    }

    public function download(Request $request, string $document): StreamedResponse
    {
        $document = $this->ownedDocument($request, $document);
        $path = AgencyDocumentStorage::path($document);
        AgencyDocumentStorage::privatize($document);
        abort_unless(Storage::disk('documents')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = Str::slug(Str::limit($document->nom, 100, '')) ?: 'document-'.$document->id;

        return Storage::disk('documents')->download($path, $name.'.'.$extension, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    public function destroy(Request $request, string $document): RedirectResponse
    {
        $document = $this->ownedDocument($request, $document);
        AgencyDocumentStorage::privatize($document);
        Storage::disk('documents')->delete(AgencyDocumentStorage::path($document));
        $document->delete();

        return back()->with('success', 'Document supprimé avec succès.');
    }

    private function ownedDocument(Request $request, string|int $id): AgenceDocument
    {
        $agenceId = $this->agencyId($request);
        $document = AgenceDocument::withoutGlobalScopes()->find($id);
        abort_if($document === null, 404);
        abort_unless((int) $document->agence_id === $agenceId, 403);

        return $document;
    }

    private function agencyId(Request $request): int
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN_AGENCE, 403);

        return (int) $request->user()->agence_id;
    }

    private function types(): array
    {
        return [
            'registre_commerce' => 'Registre de commerce',
            'identification_fiscale' => 'Carte d’identification fiscale',
            'autorisation_location' => 'Autorisation de location',
            'autre' => 'Autre document',
        ];
    }
}
