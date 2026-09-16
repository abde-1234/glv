@extends('layouts.agence')
@section('title', 'Modifier une réservation')
@section('content')
    <a class="agency-back-link" href="{{ route('agence.reservations.show', $reservation) }}"><x-icon name="arrow-left" /> Retour aux détails</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Modifier la réservation</h1><p>{{ $reservation->displayReference() }}</p></div></header>
    <form class="agency-card record-form-card" method="POST" action="{{ route('agence.reservations.update', $reservation) }}">@csrf @method('PUT') @include('agence.reservations._form')</form>
@endsection
