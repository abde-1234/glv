@extends('layouts.super-admin')
@section('title', 'Vérification email')
@section('content')
<section class="form-card">
    <h1>Vérifiez votre adresse email</h1>
    <p>Confirmez {{ auth()->user()->email }} pour accéder à l’espace Super Admin.</p>
    <form method="POST" action="{{ route('super-admin.verification.send') }}">
        @csrf
        <button class="primary-button">Envoyer le lien de vérification</button>
    </form>
</section>
@endsection
