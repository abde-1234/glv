@extends('layouts.agence')

@section('title', 'Modifier une voiture')

@section('content')
    <a class="agency-back-link" href="{{ route('agence.voitures.show', $voiture) }}"><x-icon name="arrow-left" /> Retour aux détails</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Modifier la voiture</h1><p>Mettez à jour les informations de {{ $voiture->marque }} {{ $voiture->modele }}.</p></div></header>

    <form class="agency-card vehicle-form-card" method="POST" action="{{ route('agence.voitures.update', $voiture) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('agence.voitures._form')
    </form>
@endsection
