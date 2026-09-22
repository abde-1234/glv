<?php

use App\Http\Controllers\Agence\AgenceUserController;
use App\Http\Controllers\Agence\ClientController;
use App\Http\Controllers\Agence\ContractPdfController;
use App\Http\Controllers\Agence\ContratController;
use App\Http\Controllers\Agence\DashboardController as AgenceDashboardController;
use App\Http\Controllers\Agence\DocumentController;
use App\Http\Controllers\Agence\ProfileController;
use App\Http\Controllers\Agence\ReservationController;
use App\Http\Controllers\Agence\RenewalRequestController as AgencyRenewalRequestController;
use App\Http\Controllers\Agence\SettingsController;
use App\Http\Controllers\Agence\VoitureController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SuperAdmin\AbonnementController;
use App\Http\Controllers\SuperAdmin\AgenceController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\PasswordResetRequestController;
use App\Http\Controllers\SuperAdmin\RenewalRequestController as SuperAdminRenewalRequestController;
use App\Http\Controllers\SuperAdmin\SuperAdminSettingsController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    return match ($user?->role) {
        User::ROLE_SUPER_ADMIN => redirect()->route('super-admin.dashboard'),
        User::ROLE_ADMIN_AGENCE, User::ROLE_EMPLOYE => redirect()->route('dashboard'),
        default => redirect()->route('login'),
    };
});

Route::post('/langue', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/mot-de-passe-oublie', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:password-reset-requests')
        ->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});

Route::prefix('super-admin')->name('super-admin.')->middleware(['auth', 'super_admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/parametres', [SuperAdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/parametres', [SuperAdminSettingsController::class, 'update'])->name('settings.update');
    Route::put('/parametres/securite', [SuperAdminSettingsController::class, 'saveSecurity'])->name('settings.security');
    Route::post('/parametres/admins', [SuperAdminSettingsController::class, 'storeAdmin'])->name('settings.admins.store');
    Route::put('/parametres/admins/{admin}', [SuperAdminSettingsController::class, 'updateAdmin'])->name('settings.admins.update');
    Route::get('/verification-email', [SuperAdminSettingsController::class, 'verificationNotice'])->name('verification.notice');
    Route::post('/verification-email', [SuperAdminSettingsController::class, 'sendVerification'])->middleware('throttle:3,1')->name('verification.send');
    Route::get('/verification-email/{id}/{hash}', [SuperAdminSettingsController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::patch('/agences/{agence}/suspendre', [AgenceController::class, 'suspendre'])->name('agences.suspendre');
    Route::patch('/agences/{agence}/activer', [AgenceController::class, 'activer'])->name('agences.activer');
    Route::resource('agences', AgenceController::class);
    Route::get('/abonnements', [AbonnementController::class, 'index'])->name('abonnements.index');
    Route::patch('/abonnements/{agence}', [AbonnementController::class, 'update'])->name('abonnements.update');
    Route::patch('/demandes-renouvellement/{agence}', [SuperAdminRenewalRequestController::class, 'update'])->name('renewals.update');
    Route::get('/demandes-reinitialisation', [PasswordResetRequestController::class, 'index'])->name('password-resets.index');
    Route::get('/demandes-reinitialisation/{agence}', [PasswordResetRequestController::class, 'show'])->name('password-resets.show');
    Route::patch('/demandes-reinitialisation/{agence}/approuver', [PasswordResetRequestController::class, 'approve'])->name('password-resets.approve');
    Route::patch('/demandes-reinitialisation/{agence}/refuser', [PasswordResetRequestController::class, 'reject'])->name('password-resets.reject');
});

Route::middleware(['auth', 'admin_agence'])->group(function () {
    Route::middleware('agence_subscription')->group(function () {
        Route::get('/dashboard', AgenceDashboardController::class)->name('dashboard');
        Route::resource('voitures', VoitureController::class)
            ->parameters(['voitures' => 'voiture'])
            ->names('agence.voitures');
        Route::resource('clients', ClientController::class)
            ->parameters(['clients' => 'client'])
            ->names('agence.clients');
        Route::patch('/reservations/{reservation}/annuler', [ReservationController::class, 'annuler'])->name('agence.reservations.annuler');
        Route::get('/reservations/{reservation}/contrat/create', [ContratController::class, 'createFromReservation'])->name('agence.reservations.contrat.create');
        Route::resource('reservations', ReservationController::class)
            ->parameters(['reservations' => 'reservation'])
            ->names('agence.reservations');
        Route::get('/contrats/{contrat}/pdf/preview', [ContractPdfController::class, 'preview'])->name('agence.contrats.pdf.preview');
        Route::get('/contrats/{contrat}/pdf', [ContractPdfController::class, 'download'])->name('agence.contrats.pdf.download');
        Route::patch('/contrats/{contrat}/resilier', [ContratController::class, 'resilier'])->name('agence.contrats.resilier');
        Route::resource('contrats', ContratController::class)
            ->parameters(['contrats' => 'contrat'])
            ->names('agence.contrats');
    });

    Route::get('/parametres', [SettingsController::class, 'general'])->name('agence.settings.general');
    Route::put('/parametres', [SettingsController::class, 'updateGeneral'])->name('agence.settings.general.update');
    Route::get('/parametres/contact', [SettingsController::class, 'contact'])->name('agence.settings.contact');
    Route::put('/parametres/contact', [SettingsController::class, 'updateContact'])->name('agence.settings.contact.update');
    Route::get('/parametres/abonnement', [SettingsController::class, 'subscription'])->name('agence.settings.subscription');
    Route::post('/parametres/abonnement/demande', [AgencyRenewalRequestController::class, 'store'])->name('agence.renewals.store');
    Route::get('/parametres/preferences', [SettingsController::class, 'preferences'])->name('agence.settings.preferences');
    Route::put('/parametres/preferences', [SettingsController::class, 'updatePreferences'])->name('agence.settings.preferences.update');
    Route::get('/parametres/documents', [DocumentController::class, 'index'])->name('agence.settings.documents.index');
    Route::post('/parametres/documents', [DocumentController::class, 'store'])->name('agence.settings.documents.store');
    Route::get('/parametres/documents/{document}/telecharger', [DocumentController::class, 'download'])->name('agence.settings.documents.download');
    Route::delete('/parametres/documents/{document}', [DocumentController::class, 'destroy'])->name('agence.settings.documents.destroy');
    Route::get('/parametres/utilisateurs', [AgenceUserController::class, 'index'])->name('agence.settings.users.index');
    Route::post('/parametres/utilisateurs', [AgenceUserController::class, 'store'])->name('agence.settings.users.store');
    Route::put('/parametres/utilisateurs/{user}', [AgenceUserController::class, 'update'])->name('agence.settings.users.update');
    Route::delete('/parametres/utilisateurs/{user}', [AgenceUserController::class, 'destroy'])->name('agence.settings.users.destroy');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('agence.profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('agence.profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('agence.profile.password');
});
