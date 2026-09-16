@props(['type', 'status'])

@php
    $labels = [
        'client' => ['actif' => 'Actif', 'inactif' => 'Inactif'],
        'reservation' => ['confirmee' => 'Confirmée', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'],
        'contrat' => ['actif' => 'Actif', 'termine' => 'Terminé', 'annule' => 'Annulé'],
        'agency' => ['actif' => 'Actif', 'essai' => 'Essai', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'],
        'user' => ['actif' => 'Actif', 'inactif' => 'Inactif'],
    ];
@endphp

<span {{ $attributes->class(['record-status', 'is-'.$status]) }}><i></i>{{ $labels[$type][$status] ?? ucfirst($status) }}</span>
