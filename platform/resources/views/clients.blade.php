@extends('layouts.app')
@section('title', 'Choose client')
@section('content')
<section class="panel"><h1>Choose your client</h1><p>Select the organisation you want to work in.</p>
@forelse($clients as $client)
<form method="post" action="{{ route('clients.select') }}">@csrf<input type="hidden" name="client_id" value="{{ $client->id }}"><button type="submit">{{ $client->name }}</button></form>
@empty<p>No active client access is available. Contact your administrator.</p>@endforelse
</section>
@endsection
