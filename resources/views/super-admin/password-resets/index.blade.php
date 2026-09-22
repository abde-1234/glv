@extends('layouts.super-admin')
@section('title', 'Demandes de réinitialisation')
@section('content')
    <div class="page-heading"><div><p class="page-eyebrow">Sécurité des accès</p><h1>Demandes de réinitialisation</h1><p>Examinez les demandes des administrateurs d’agence sans modifier leur abonnement.</p></div></div>
    <section class="content-card list-card">
        <form class="filter-bar" method="GET" action="{{ route('super-admin.password-resets.index') }}">
            <label class="filter-search"><x-icon name="search" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Agence, nom ou e-mail…" aria-label="Rechercher"></label>
            <label><span class="sr-only">Statut</span><select name="status"><option value="">Tous les statuts</option>@foreach (['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Refusée', 'completed' => 'Terminée', 'expired' => 'Expirée'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="filter-button" type="submit">Filtrer</button>
            @if (request()->hasAny(['q', 'status']))<a class="reset-filter" href="{{ route('super-admin.password-resets.index') }}">Réinitialiser</a>@endif
        </form>
        <div class="table-scroll reset-request-table-scroll"><table class="data-table reset-request-table"><thead><tr><th>Agence</th><th>Administrateur</th><th>Email</th><th>Date de demande</th><th>Statut</th><th>Traité par</th><th>Date de traitement</th><th>Action</th></tr></thead><tbody>
            @forelse ($requests as $resetRequest)
                <tr><td data-label="Agence"><strong>{{ $resetRequest->agence->nom }}</strong><br><small>{{ $resetRequest->agence->ville ?: '—' }}</small></td><td data-label="Administrateur"><strong>{{ $resetRequest->user->name }}</strong></td><td data-label="Email">{{ $resetRequest->user->email }}</td><td data-label="Demandée le"><time datetime="{{ $resetRequest->requested_at->toAtomString() }}">{{ $resetRequest->requested_at->format('d/m/Y H:i') }}</time></td><td data-label="Statut"><span class="reset-status is-{{ $resetRequest->status }}">{{ ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Refusée', 'completed' => 'Terminée', 'expired' => 'Expirée'][$resetRequest->status] }}</span></td><td data-label="Traitée par">{{ $resetRequest->processor?->name ?? '—' }}</td><td data-label="Traitée le">{{ $resetRequest->processed_at?->format('d/m/Y H:i') ?? '—' }}</td><td data-label="Action"><a class="table-edit-button" href="{{ route('super-admin.password-resets.show', $resetRequest) }}"><x-icon name="eye" /> {{ $resetRequest->status === 'pending' ? 'Traiter' : 'Consulter' }}</a></td></tr>
            @empty
                <tr class="reset-request-empty-row"><td colspan="8"><div class="empty-state"><strong>Aucune demande</strong><span>Aucune demande ne correspond aux filtres choisis.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if ($requests->hasPages())<div class="pagination-wrap">{{ $requests->links() }}</div>@endif
    </section>
@endsection
