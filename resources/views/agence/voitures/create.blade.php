@extends('layouts.agence')

@section('title', 'Ajouter une voiture')

@section('content')
    <a class="agency-back-link" href="{{ route('agence.voitures.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Ajouter une voiture</h1><p>Remplissez les informations du véhicule.</p></div></header>

    <form class="agency-card vehicle-form-card" method="POST" action="{{ route('agence.voitures.store') }}" enctype="multipart/form-data">
        @csrf
        @include('agence.voitures._form', ['voiture' => new \App\Models\Voiture])
    </form>
@endsection
