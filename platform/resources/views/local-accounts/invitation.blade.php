@extends('layouts.app')
@section('title', 'Client invitation')
@section('content')
<section class="panel"><h1>Join {{ $client->name }}</h1><p>You are signed in as {{ auth()->user()->email }}.</p><p>Accepting gives you Member access to this client. Your other clients and training history are unchanged. Sign in again after accepting.</p>
<form method="post" action="{{ route('local.invitation.accept', $token) }}">@csrf
<label><input type="checkbox" name="accept" value="1" required> I accept Member access to {{ $client->name }}.</label><button class="primary">Accept invitation</button></form>
<a href="{{ route('clients.show') }}">Leave without accepting</a></section>
@endsection
