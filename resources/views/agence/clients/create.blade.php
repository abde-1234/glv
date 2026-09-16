@extends('layouts.agence')
@section('title', 'Ajouter un client')
@section('content')
    <a class="agency-back-link" href="{{ route('agence.clients.index') }}"><x-icon name="arrow-left" /> Retour à la liste</a>
    <header class="agency-page-heading vehicle-form-heading"><div><h1>Ajouter un client</h1><p>Enregistrez les informations essentielles du client.</p></div></header>
    <form class="agency-card record-form-card" method="POST" action="{{ route('agence.clients.store') }}">@csrf @include('agence.clients._form', ['client' => new \App\Models\Client])</form>
@endsection
