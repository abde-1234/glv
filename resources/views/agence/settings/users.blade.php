@extends('layouts.agence')
@section('title', 'Gestion des utilisateurs')
@section('content')
    <header class="agency-page-heading settings-page-heading">
        <div><h1>Gestion des utilisateurs</h1><p>Gérez les accès des membres de votre agence.</p></div>
        <button class="agency-primary-button" type="button" data-modal-open="create-agency-user"><x-icon name="plus" width="18" height="18" /> Ajouter un utilisateur</button>
    </header>
    <div class="agency-settings-layout">
        <x-agency-settings-nav />
        <section class="agency-card settings-panel users-panel" aria-label="Utilisateurs de l’agence">
            @if ($errors->has('user'))<div class="settings-form-error users-global-error" role="alert">{{ $errors->first('user') }}</div>@endif
            <div class="agency-table-scroll">
                <table class="agency-table settings-users-table">
                    <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td><div class="record-person"><span aria-hidden="true">{{ str($managedUser->name)->substr(0, 2)->upper() }}</span><strong>{{ $managedUser->name }}</strong></div></td>
                                <td>{{ $managedUser->email }}</td>
                                <td>{{ $managedUser->telephone ?: '—' }}</td>
                                <td><span class="user-role-badge">{{ $managedUser->role === App\Models\User::ROLE_ADMIN_AGENCE ? ($managedUser->is(auth()->user()) ? 'Gérant' : 'Admin agence') : 'Employé' }}</span></td>
                                <td><x-status-badge type="user" :status="$managedUser->statut" /></td>
                                <td>
                                    <div class="vehicle-row-actions">
                                        <button type="button" data-modal-open="edit-agency-user-{{ $managedUser->id }}" aria-label="Modifier {{ $managedUser->name }}"><x-icon name="edit" width="16" height="16" /></button>
                                        <form method="POST" action="{{ route('agence.settings.users.destroy', $managedUser) }}" data-confirm-delete="Supprimer cet utilisateur ?">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Supprimer {{ $managedUser->name }}" @disabled($managedUser->is(auth()->user()))><x-icon name="trash" width="16" height="16" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="settings-empty">Aucun utilisateur dans votre agence.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog class="agency-modal" id="create-agency-user" aria-labelledby="create-user-title" {{ old('_user_form') === 'create' ? 'data-auto-open' : '' }}>
        <form method="POST" action="{{ route('agence.settings.users.store') }}">
            @csrf
            <input type="hidden" name="_user_form" value="create">
            <header>
                <div><h2 id="create-user-title">Ajouter un utilisateur</h2><p>Créez un accès pour un membre de l’agence.</p></div>
                <button type="button" data-modal-close aria-label="Fermer"><x-icon name="close" width="18" height="18" /></button>
            </header>
            @include('agence.settings._user-fields', ['managedUser' => null])
            <footer><button class="agency-secondary-button" type="button" data-modal-close>Annuler</button><button class="agency-primary-button" type="submit">Ajouter l’utilisateur</button></footer>
        </form>
    </dialog>

    @foreach ($users as $managedUser)
        <dialog class="agency-modal" id="edit-agency-user-{{ $managedUser->id }}" aria-labelledby="edit-user-title-{{ $managedUser->id }}" {{ old('_user_form') === 'edit-'.$managedUser->id ? 'data-auto-open' : '' }}>
            <form method="POST" action="{{ route('agence.settings.users.update', $managedUser) }}">
                @csrf @method('PUT')
                <input type="hidden" name="_user_form" value="edit-{{ $managedUser->id }}">
                <header>
                    <div><h2 id="edit-user-title-{{ $managedUser->id }}">Modifier l’utilisateur</h2><p>{{ $managedUser->name }}</p></div>
                    <button type="button" data-modal-close aria-label="Fermer"><x-icon name="close" width="18" height="18" /></button>
                </header>
                @include('agence.settings._user-fields', ['managedUser' => $managedUser])
                <footer><button class="agency-secondary-button" type="button" data-modal-close>Annuler</button><button class="agency-primary-button" type="submit">Enregistrer</button></footer>
            </form>
        </dialog>
    @endforeach
@endsection
