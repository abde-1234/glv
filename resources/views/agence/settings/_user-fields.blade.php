@php
    $formKey = $managedUser ? 'edit-'.$managedUser->id : 'create';
    $hasOldInput = old('_user_form') === $formKey;
    $isSelf = $managedUser?->is(auth()->user()) ?? false;
    $formValue = fn ($field, $default = '') => $hasOldInput ? old($field, $default) : ($managedUser?->{$field} ?? $default);
@endphp
<div class="modal-form-grid">
    <label class="agency-form-field">
        <span>Nom complet <b>*</b></span>
        <input type="text" name="name" value="{{ $formValue('name') }}" maxlength="255" required>
        @if ($hasOldInput && $errors->has('name'))<small role="alert">{{ $errors->first('name') }}</small>@endif
    </label>
    <label class="agency-form-field">
        <span>Email <b>*</b></span>
        <input type="email" name="email" value="{{ $formValue('email') }}" maxlength="255" required>
        @if ($hasOldInput && $errors->has('email'))<small role="alert">{{ $errors->first('email') }}</small>@endif
    </label>
    <label class="agency-form-field">
        <span>Téléphone</span>
        <input type="tel" name="telephone" value="{{ $formValue('telephone') }}" maxlength="30">
        @if ($hasOldInput && $errors->has('telephone'))<small role="alert">{{ $errors->first('telephone') }}</small>@endif
    </label>
    <label class="agency-form-field">
        <span>Rôle <b>*</b></span>
        <select name="role" required @disabled($isSelf)>
            <option value="employe" @selected($formValue('role', 'employe') === 'employe')>Employé</option>
            <option value="admin_agence" @selected($formValue('role', 'employe') === 'admin_agence')>Admin Agence</option>
        </select>
        @if ($isSelf)<input type="hidden" name="role" value="admin_agence">@endif
        @if ($hasOldInput && $errors->has('role'))<small role="alert">{{ $errors->first('role') }}</small>@endif
    </label>
    @if ($managedUser)
        <label class="agency-form-field">
            <span>Statut <b>*</b></span>
            <select name="statut" required @disabled($isSelf)>
                <option value="actif" @selected($formValue('statut', 'actif') === 'actif')>Actif</option>
                <option value="inactif" @selected($formValue('statut', 'actif') === 'inactif')>Inactif</option>
            </select>
            @if ($isSelf)<input type="hidden" name="statut" value="actif">@endif
            @if ($hasOldInput && $errors->has('statut'))<small role="alert">{{ $errors->first('statut') }}</small>@endif
        </label>
    @endif
    <label class="agency-form-field {{ $managedUser ? '' : 'full' }}">
        <span>{{ $managedUser ? 'Nouveau mot de passe' : 'Mot de passe temporaire' }} @unless($managedUser)<b>*</b>@endunless</span>
        <input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" @required(! $managedUser) placeholder="{{ $managedUser ? 'Laisser vide pour le conserver' : '8 caractères minimum' }}">
        @if ($hasOldInput && $errors->has('password'))<small role="alert">{{ $errors->first('password') }}</small>@endif
    </label>
</div>
