@extends('layouts.app')
@section('title', 'Account action unavailable')
@section('content')
<section class="panel"><h1>{{ $status === 410 ? 'Link unavailable' : 'Account action unavailable' }}</h1>
@if($status === 410)<p>This link has expired, was cancelled, or has already been used. Ask your administrator for a new private link.</p>
@elseif($status === 403)<p>This account cannot complete the action, or the administrator's authority has changed. For an invitation, sign in as the intended recipient and reopen the link. Otherwise, contact your administrator.</p>
@else<p>The account or its access has changed. Reload the administration screen and review the current account before trying again. Existing accounts must accept an invitation before joining another client.</p>@endif
<a href="{{ route('login') }}">Return to sign in</a></section>
@endsection
