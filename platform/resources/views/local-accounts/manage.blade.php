@extends('layouts.app')
@section('title', 'Account links')
@section('content')
<section class="panel"><h1>Account links</h1><p>Recovery changes the shared login across every client and ends all sessions when completed. Client Admins cannot recover themselves or Global Admins.</p>
@php($retryRecovery = old('_form') === 'local-account-recovery')
<form method="post" action="{{ route('local.actions') }}">@csrf<input type="hidden" name="_form" value="local-account-recovery">
<label>Action<select name="action" required><option value="">Choose action…</option><option value="reset" @selected($retryRecovery && old('action') === 'reset')>Issue recovery link</option><option value="setup" @selected($retryRecovery && old('action') === 'setup')>Reissue initial setup</option></select></label>
<label>Account<select name="target" required><option value="">Choose account…</option>@foreach($targets as $target)<option value="{{ $target->id }}" @selected($retryRecovery && old('target') === $target->id)>{{ $target->name }} — {{ $target->email }}</option>@endforeach</select></label>
@include('local-accounts.delivery-option')
@if($global)<label>Client (required for initial setup)<select name="client"><option value="">Account-wide recovery</option>@foreach($clientChoices as $choice)<option value="{{ $choice->id }}" @selected(($retryRecovery ? old('client') : $client) === $choice->id)>{{ $choice->name }}</option>@endforeach</select></label>@else<input type="hidden" name="client" value="{{ $client }}">@endif
<label>Your current password<input name="current_password" type="password" autocomplete="current-password" required maxlength="128"></label><label>Reason<input name="reason" value="{{ $retryRecovery ? old('reason') : '' }}" required minlength="8" maxlength="2000"></label><button class="primary">Issue private link</button></form></section>
<section class="panel"><h2>Issued links</h2>
@forelse($links as $link)<article><h3>{{ ucfirst($link->purpose) }} — {{ \App\Models\User::find($link->user_id)?->name ?? 'Account' }}</h3><p>{{ $link->consumed_at ? 'Used' : ($link->revoked_at ? 'Cancelled' : (now()->gte($link->expires_at) ? 'Expired' : 'Pending')) }} · Expires {{ $link->expires_at }} UTC</p>
@if(!$link->consumed_at && !$link->revoked_at)<form method="post" action="{{ route('local.actions') }}">@csrf<input type="hidden" name="action" value="cancel"><input type="hidden" name="link" value="{{ $link->id }}"><input type="hidden" name="client" value="{{ $link->organisation_id }}"><label>Your current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>Cancellation reason<input name="reason" minlength="8" maxlength="2000" required></label><button class="secondary">Cancel link</button></form>@endif</article>@empty<p>No issued links.</p>@endforelse
{{ $links->links() }}</section>
@endsection
