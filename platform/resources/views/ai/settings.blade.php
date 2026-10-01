@extends('layouts.app')
@section('title', 'AI settings')
@section('content')
<h1>AI provider settings</h1>
<p>Routing changes apply to new requests. Existing requests retain their exact configuration. Only curriculum generation is connected to a workflow; grading, adaptation and KPI routes are reserved for later milestones.</p>
<section class="panel"><h2>Provider registry</h2><p>Models, data policies, limits, pricing and secret references are deployment-controlled in <code>config/ai.php</code>. No credentials are displayed.</p>
<ul>@foreach($providers as $id=>$provider)<li><strong>{{ $id }}</strong> · {{ $provider['enabled'] ? 'Enabled' : 'Disabled pending qualification' }} · {{ $provider['model'] ?: 'Model not configured' }} · {{ $provider['context_bytes'] }} input bytes / {{ $provider['max_output_tokens'] }} output tokens</li>@endforeach</ul>
<p>Lifetime organisation cap: {{ config('ai.organisation_limit') }} micro-USD. Per-programme cap: {{ config('ai.programme_limit') }} micro-USD. Reservations and accounted usage both count. Mock charges are simulated.</p></section>
@foreach($policies as $task=>$policy)
<section class="panel"><h2>{{ ucfirst(str_replace('_',' ',$task)) }}</h2><form method="post" action="{{ route('ai.policy') }}">@csrf
<input type="hidden" name="task" value="{{ $task }}"><input type="hidden" name="expected_version" value="{{ $policy?->version ?? 0 }}">
<label>Primary provider<select name="primary">@foreach($providers as $id=>$provider)@if($provider['enabled'] && in_array($task,$provider['purposes']))<option value="{{ $id }}" @selected(($policy?->routing[0] ?? 'mock') === $id)>{{ $id }}</option>@endif @endforeach</select></label>
<label>Explicitly permitted fallback<select name="fallback"><option value="">None</option>@foreach($providers as $id=>$provider)@if($provider['enabled'] && in_array($task,$provider['purposes']))<option value="{{ $id }}" @selected(($policy?->routing[1] ?? '') === $id)>{{ $id }}</option>@endif @endforeach</select></label>
<button class="primary">Save {{ str_replace('_',' ',$task) }} route</button></form></section>
@endforeach
<section class="panel"><h2>Uncertain charges</h2><p>Reconcile against provider billing evidence. Enter the total for the entire run, including already accounted calls. This releases the hold and permits a separately requested retry.</p>
@forelse($ambiguous as $run)<form class="lesson" method="post" action="{{ route('ai.reconcile',$run->id) }}">@csrf<p>Run {{ $run->id }} · Known usage {{ $run->spent }} · Reserved {{ $run->reserved }} micro-USD</p><label>Verified total (micro-USD)<input name="total" type="number" min="{{ $run->spent }}" required></label><label>Billing reference and reason<input name="reason" minlength="8" maxlength="2000" required></label><button class="primary">Record reconciliation</button></form>@empty<p>No charges awaiting reconciliation.</p>@endforelse</section>
@endsection
