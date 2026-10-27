@extends('layouts.app')
@section('title','Review your change')
@section('content')
<section class="panel"><h1>Review your change</h1><p role="alert">{{ $message ?: 'These settings changed while you were editing.' }}</p><p>No change was saved. Reload the relevant page and review its current values before trying again.</p><a class="button" href="{{ route('platform.overview') }}">Return to platform administration</a></section>
@endsection
