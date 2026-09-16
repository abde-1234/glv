@extends('layouts.agence')
@section('title', 'Modifier un client')
@section('content')
    <a class="agency-back-link" href="{{ route('agence.clients.show', $client) }}"><x-icon name="arrow-left" /> Retour aux détails</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Modifier le client</h1><p>Mettez à jour les informations de {{ $client->nom }}.</p></div></header>
    <form class="agency-card record-form-card" method="POST" action="{{ route('agence.clients.update', $client) }}">@csrf @method('PUT') @include('agence.clients._form')</form>
@endsection
