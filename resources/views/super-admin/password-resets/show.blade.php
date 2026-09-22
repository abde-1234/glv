@extends('layouts.super-admin')
@section('title', 'Demande de réinitialisation')
@section('content')
    <div class="page-heading"><div><p class="page-eyebrow">Sécurité des accès</p><h1>Demande #{{ $resetRequest->id }}</h1><p>Vérifiez l’identité et l’agence avant de prendre une décision.</p></div><a class="secondary-button" href="{{ route('super-admin.password-resets.index') }}">← Retour à la liste</a></div>
    @if ($errors->any())<div class="flash-message is-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="reset-request-layout">
        <section class="content-card reset-request-details">
            <div class="card-heading"><div><span class="section-icon"><x-icon name="lock" /></span><h2>Informations contrôlées</h2></div><span class="reset-status is-{{ $resetRequest->status }}">{{ ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Refusée', 'completed' => 'Terminée', 'expired' => 'Expirée'][$resetRequest->status] }}</span></div>
            <dl class="detail-list"><div><dt>Agence</dt><dd>{{ $resetRequest->agence->nom }}</dd></div><div><dt>État de l’agence</dt><dd>{{ ucfirst($resetRequest->agence->subscriptionStatus()) }}</dd></div><div><dt>Administrateur</dt><dd>{{ $resetRequest->user->name }}</dd></div><div><dt>Adresse e-mail</dt><dd>{{ $resetRequest->user->email }}</dd></div><div><dt>Demandée le</dt><dd>{{ $resetRequest->requested_at->format('d/m/Y à H:i') }}</dd></div><div><dt>Expiration de l’étape</dt><dd>{{ $resetRequest->expires_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>@if ($resetRequest->processor)<div><dt>Traitée par</dt><dd>{{ $resetRequest->processor->name }}</dd></div>@endif @if ($resetRequest->processed_at)<div><dt>Traitée le</dt><dd>{{ $resetRequest->processed_at->format('d/m/Y à H:i') }}</dd></div>@endif @if ($resetRequest->rejection_reason)<div><dt>Motif du refus</dt><dd>{{ $resetRequest->rejection_reason }}</dd></div>@endif</dl>
            <section class="reset-history" aria-labelledby="reset-history-title">
                <h3 id="reset-history-title">Historique</h3>
                @php($eventLabels = ['created' => 'Demande créée', 'approved' => 'Demande approuvée', 'rejected' => 'Demande refusée', 'expired' => 'Demande expirée', 'reset_used' => 'Lien utilisé', 'password_changed' => 'Mot de passe modifié'])
                @forelse ($resetRequest->events as $event)
                    <div class="reset-history-item"><span aria-hidden="true"></span><p><strong>{{ $eventLabels[$event->event_type] ?? $event->event_type }}</strong><small>{{ $event->created_at->format('d/m/Y à H:i:s') }} · {{ $event->actor?->name ?? 'Système' }}</small></p></div>
                @empty
                    <p class="reset-history-empty">Aucun événement détaillé n’est disponible pour cette demande antérieure.</p>
                @endforelse
            </section>
            <div class="tip-box"><x-icon name="info" /><span><strong>Impact limité à l’accès</strong><small>Cette action ne réactive ni l’agence ni son abonnement. Le lien envoyé est personnel, temporaire et à usage unique.</small></span></div>
        </section>
        @if ($resetRequest->status === 'pending')
            <aside class="content-card reset-decision-card"><div class="card-heading"><div><h2>Décision</h2></div></div><form method="POST" action="{{ route('super-admin.password-resets.approve', $resetRequest) }}" data-submit-once>@csrf @method('PATCH')<p>Cette action envoie un lien sécurisé à l’adresse enregistrée. Le mot de passe n’est jamais communiqué en clair.</p><button class="primary-button full-button" type="submit" data-loading-text="Approbation…"><span data-button-label>Réinitialiser le mot de passe</span></button></form><hr><form method="POST" action="{{ route('super-admin.password-resets.reject', $resetRequest) }}" data-submit-once>@csrf @method('PATCH')<label class="form-field"><span>Motif du refus <b>*</b></span><textarea name="rejection_reason" rows="4" maxlength="2000" required>{{ old('rejection_reason') }}</textarea></label><button class="danger-button full-button" type="submit" data-loading-text="Refus…"><span data-button-label>Refuser la demande</span></button></form></aside>
        @endif
    </div>
@endsection
