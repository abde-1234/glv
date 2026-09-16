@props(['status'])

@php
    $labels = [
        'disponible' => 'Disponible',
        'loue' => 'Louée',
        'maintenance' => 'Maintenance',
        'indisponible' => 'Indisponible',
    ];
@endphp

<span {{ $attributes->class(['vehicle-status', 'is-'.$status]) }}>
    <i></i>{{ $labels[$status] ?? ucfirst($status) }}
</span>
