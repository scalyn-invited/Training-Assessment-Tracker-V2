@extends('layouts.app')
@section('title', 'Link unavailable')
@section('content')
<section class="panel"><h1>Link unavailable</h1><p>This link has expired, was cancelled, or has already been used. Ask your administrator for a new private link.</p><a href="{{ route('login') }}">Return to sign in</a></section>
@endsection
