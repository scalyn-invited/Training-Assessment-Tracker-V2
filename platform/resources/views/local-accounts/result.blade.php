@extends('layouts.app')
@section('title', 'Account link')
@section('content')
<section class="panel"><h1>{{ $result ? 'Private account link' : 'Link cancelled' }}</h1>
@if($delivery ?? null)<p role="status">{{ $delivery }}</p>@endif
@if($result)<p>This {{ $result['purpose'] }} link is shown once. Share it privately with the intended person. {{ $result['purpose'] === 'invite' ? 'Expires in seven days.' : 'Expires in one hour.' }}</p>
<label>Private link<input readonly value="{{ $result['url'] }}" aria-describedby="link-warning"></label>
<p id="link-warning">Do not put this link in a ticket, public message, or screenshot. Reloading this page is unnecessary; reissuing cancels the previous link.</p>@endif
<a href="{{ route('local.links') }}">Manage account links</a></section>
@endsection
