@extends('layouts.agence')
@section('title', 'Nouvelle réservation')
@section('content')
    <a class="agency-back-link" href="{{ route('agence.reservations.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Nouvelle réservation</h1><p>Créez une nouvelle réservation.</p></div></header>
    <form class="agency-card record-form-card" method="POST" action="{{ route('agence.reservations.store') }}">@csrf @include('agence.reservations._form', ['reservation' => new \App\Models\Reservation])</form>
@endsection
