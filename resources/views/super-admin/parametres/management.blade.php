<section class="form-card">
    <div class="form-section-title"><span><x-icon name="settings" /></span><div><h2>Système et sécurité</h2><p>Règles d’accès à l’espace Super Admin.</p></div></div>
    <form method="POST" action="{{ route('super-admin.settings.security') }}">
        @csrf @method('PUT')
        <div class="form-grid two-columns">
            <label class="form-field"><span>Fuseau horaire plateforme</span><select name="timezone">@foreach (DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected(old('timezone', $system['timezone']) === $zone)>{{ $zone }}</option>@endforeach</select></label>
            <label class="form-field"><span>Expiration après inactivité (minutes)</span><input type="number" min="5" max="1440" name="session_minutes" value="{{ old('session_minutes', $system['session_minutes']) }}" required></label>
            <label class="form-field"><span>Vérification email Super Admin</span><select name="email_verification"><option value="0" @selected(old('email_verification', $system['email_verification']) == '0')>Facultative</option><option value="1" @selected(old('email_verification', $system['email_verification']) == '1')>Obligatoire avant accès</option></select><small>Les comptes non vérifiés devront confirmer leur adresse par email.</small></label>
            <label class="form-field"><span>Votre mot de passe actuel</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        </div>
        <div class="sa-settings-actions"><button class="primary-button">Enregistrer</button></div>
    </form>
</section>
<section class="form-card sa-admin-management">
    <div class="form-section-title"><span><x-icon name="users" /></span><div><h2>Gestion Super Admins</h2><p>Comptes globaux autorisés. Votre propre compte ne peut pas être désactivé.</p></div></div>
    @foreach ($admins as $admin)
        <details class="sa-settings-password" @if(old('admin_id') == $admin->id) open @endif>
            <summary>{{ $admin->name }} — {{ $admin->email }} · {{ $admin->statut === 'actif' ? 'Actif' : 'Désactivé' }}</summary>
            <form method="POST" action="{{ route('super-admin.settings.admins.update', $admin) }}">
                @csrf @method('PUT')
                <input type="hidden" name="admin_id" value="{{ $admin->id }}">
                @include('super-admin.parametres.admin-fields', ['editing' => $admin])
                <div class="sa-settings-actions"><button class="primary-button">Enregistrer les modifications</button></div>
            </form>
        </details>
    @endforeach
    {{ $admins->links() }}
    <details class="sa-settings-password" @if(old('admin_id') === 'new') open @endif>
        <summary>Ajouter un Super Admin</summary>
        <form method="POST" action="{{ route('super-admin.settings.admins.store') }}">
            @csrf
            <input type="hidden" name="admin_id" value="new">
            @include('super-admin.parametres.admin-fields', ['editing' => null])
            <div class="sa-settings-actions"><button class="primary-button">Ajouter</button></div>
        </form>
    </details>
</section>
