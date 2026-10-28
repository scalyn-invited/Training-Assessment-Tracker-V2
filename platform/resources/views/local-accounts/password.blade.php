@extends('layouts.app')
@section('title', 'Password')
@section('content')
<section class="panel"><h1>{{ $purpose === 'change' ? 'Change password' : ($purpose === 'setup' ? 'Set your password' : 'Reset your password') }}</h1>
<p>Use at least 15 characters. Saving ends all sessions. You will sign in again.</p>
<form method="post" action="{{ $purpose === 'change' ? route('local.password.change') : route('local.password.save', [$purpose, $token]) }}">@csrf
@if($purpose === 'change')<label>Current password<input name="current_password" type="password" autocomplete="current-password" required maxlength="128"></label>@endif
<label>New password<input name="password" type="password" autocomplete="new-password" minlength="15" maxlength="128" required></label>
<label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="15" maxlength="128" required></label>
<button class="primary">Save password</button></form></section>
@endsection
