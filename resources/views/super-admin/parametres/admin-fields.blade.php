@php($activeForm = (string) old('admin_id') === (string) ($editing?->id ?? 'new'))
<div class="form-grid two-columns">
    <label class="form-field"><span>Nom</span><input name="admin_name" maxlength="255" value="{{ $activeForm ? old('admin_name') : $editing?->name }}" required></label>
    <label class="form-field"><span>Email</span><input type="email" name="admin_email" maxlength="255" value="{{ $activeForm ? old('admin_email') : $editing?->email }}" required></label>
    <label class="form-field"><span>{{ $editing ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe' }}</span><input type="password" name="admin_password" autocomplete="new-password" minlength="12" maxlength="72" @required(!$editing)></label>
    <label class="form-field"><span>Confirmation</span><input type="password" name="admin_password_confirmation" autocomplete="new-password" @required(!$editing)></label>
    @if($editing)
        <label class="form-field"><span>Accès Super Admin</span><select name="admin_status"><option value="actif" @selected($editing->statut === 'actif')>Actif</option>@if(!$editing->is($user))<option value="inactif" @selected($editing->statut === 'inactif')>Désactivé</option>@endif</select></label>
    @endif
    <label class="form-field"><span>Votre mot de passe actuel</span><input type="password" name="current_password" autocomplete="current-password" required></label>
</div>
