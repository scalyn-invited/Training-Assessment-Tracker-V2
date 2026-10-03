@extends('layouts.app')
@section('title','Training updates')
@section('content')
<h1>Training updates</h1><section class="panel"><h2>Email preference</h2><form method="post" action="{{ route('notifications.preference') }}">@csrf<label>Training emails<select name="training_email"><option value="1" @selected(auth()->user()->training_email)>Enabled when a live sender is configured</option><option value="0" @selected(!auth()->user()->training_email)>In-app updates only</option></select></label><button class="secondary">Save preference</button></form><p>Synthetic accounts use a non-delivering sink. Relay acceptance does not prove email delivery.</p></section>
<section class="panel"><h2>Your updates</h2><ul>@forelse($notices as $notice)<li><a href="{{ route('delivery.show',$notice->enrolment_id) }}">{{ str_replace('_',' ',$notice->kind) }}</a> · {{ $notice->created_at }} · {{ str_replace('_',' ',$notice->status) }}</li>@empty<li>No updates yet.</li>@endforelse</ul>{{ $notices->links() }}</section>
@endsection
