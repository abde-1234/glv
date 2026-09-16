@extends('layouts.agence')
@section('title', 'Documents')
@section('content')
    <header class="agency-page-heading settings-page-heading"><div><h1>Documents</h1><p>Gérez les documents officiels de votre agence.</p></div></header>
    <div class="agency-settings-layout">
        <x-agency-settings-nav />
        <div class="settings-stack">
            <form class="agency-card settings-panel" method="POST" action="{{ route('agence.settings.documents.store') }}" enctype="multipart/form-data">
                @csrf
                <h2>Ajouter un document</h2>
                <div class="settings-form-grid">
                    <label class="agency-form-field"><span>Nom <b>*</b></span><input type="text" name="nom" value="{{ old('nom') }}" required maxlength="255">@error('nom')<small>{{ $message }}</small>@enderror</label>
                    <label class="agency-form-field"><span>Type <b>*</b></span><select name="type" required>@foreach ($documentTypes as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select>@error('type')<small>{{ $message }}</small>@enderror</label>
                    <label class="agency-form-field full"><span>Fichier <b>*</b> <small>PDF, JPG ou PNG · 5 Mo maximum</small></span><input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png" required>@error('fichier')<small>{{ $message }}</small>@enderror</label>
                </div>
                <footer class="settings-form-actions"><button class="agency-primary-button" type="submit"><x-icon name="plus" width="18" height="18" /> Ajouter le document</button></footer>
            </form>
            <section class="agency-card settings-documents-card" aria-label="Documents de l’agence">
                <header class="settings-table-heading"><h2>Documents de l’agence</h2><span>{{ $documents->count() }} document(s)</span></header>
                <div class="agency-table-scroll">
                    <table class="agency-table settings-documents-table">
                        <thead><tr><th>Nom</th><th>Type</th><th>Taille</th><th>Date d’ajout</th><th>Actions</th></tr></thead>
                        <tbody>
                            @forelse ($documents as $document)
                                <tr>
                                    <td><strong>{{ $document->nom }}</strong></td>
                                    <td>{{ $documentTypes[$document->type] ?? 'Autre document' }}</td>
                                    <td>{{ $document->taille ? ($document->taille >= 1048576 ? number_format($document->taille / 1048576, 1, ',', ' ').' Mo' : number_format($document->taille / 1024, 0, ',', ' ').' Ko') : '—' }}</td>
                                    <td>{{ $document->created_at->format('d/m/Y') }}</td>
                                    <td><div class="vehicle-row-actions">
                                        <a href="{{ route('agence.settings.documents.download', $document) }}" title="Télécharger" aria-label="Télécharger {{ $document->nom }}"><x-icon name="upload" class="settings-download-icon" width="16" height="16" /></a>
                                        <form method="POST" action="{{ route('agence.settings.documents.destroy', $document) }}" data-confirm-delete="Supprimer ce document ?">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer {{ $document->nom }}"><x-icon name="trash" width="16" height="16" /></button></form>
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="settings-empty">Aucun document. Ajoutez votre premier document ci-dessus.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
