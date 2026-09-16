@extends('layouts.super-admin')

@section('title', 'Modifier une agence')

@section('content')
    <div class="page-heading">
        <div><p class="page-eyebrow">Agences</p><h1>Modifier {{ $agence->nom }}</h1><p>Mettez à jour les informations de l’agence et de son gérant.</p></div>
        <a class="secondary-button" href="{{ route('super-admin.agences.show', $agence) }}"><x-icon name="arrow-left" /> Retour à la fiche</a>
    </div>
    @include('super-admin.agences._form', ['agence' => $agence])
@endsection
