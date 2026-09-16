@extends('layouts.super-admin')

@section('title', 'Ajouter une agence')

@section('content')
    <div class="page-heading">
        <div><p class="page-eyebrow">Agences</p><h1>Ajouter une agence</h1><p>Créez l’agence et son compte gérant en une seule étape.</p></div>
        <a class="secondary-button" href="{{ route('super-admin.agences.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    </div>
    @include('super-admin.agences._form')
@endsection
