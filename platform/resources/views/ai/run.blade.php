@extends('layouts.app')
@section('title', 'Curriculum generation')
@section('content')
<a class="back" href="{{ route('plans.show', $enrolment->id) }}">Back to curriculum</a>
<div class="page-heading"><div><p class="eyebrow">Draft generation</p><h1>{{ $enrolment->title }}</h1><p>Request {{ $run->id }}</p></div></div>
<section class="panel" data-generation-url="{{ route('ai.show', $run->id) }}" data-active="{{ in_array($run->status, ['queued','running']) ? 'true' : 'false' }}">
<h2>Generation progress</h2><p id="generation-status" role="status">{{ $run->message }}</p>
<p id="generation-progress">{{ $blocks->where('status','complete')->count() }} of {{ $blocks->count() }} blocks complete · {{ $run->status }}</p>
<p id="generation-cost">Reserved: {{ $run->reserved }} micro-USD · Accounted usage: {{ $run->spent }} micro-USD</p>
<p class="subtle">1,000,000 micro-USD = USD 1. Mock amounts are simulated ledger entries, not actual charges. Uncertain charges remain reserved until reconciled.</p>
<p><a id="generation-result" @if(!$result) hidden @endif href="{{ $result ? route('plans.show', ['id'=>$enrolment->id,'version'=>$result->version]) : '#' }}">Review generated draft</a></p>
<p><a href="{{ route('ai.show', $run->id) }}">Refresh full details</a></p>
</section>
<section class="panel"><h2>Configuration and provenance</h2><p>Policy version {{ $run->configuration['policy_version'] }} · Prompt {{ $run->configuration['prompt_version'] }} · Schema {{ $run->configuration['schema_version'] }}</p>
<ul>@foreach($run->configuration['providers'] as $provider)<li>{{ $provider['id'] }} · {{ $provider['model'] }} · Configuration {{ $provider['version'] }} · Rate {{ $provider['rate_version'] }} @if($loop->index > 0)(permitted fallback)@endif</li>@endforeach</ul>
<p>Revision notes: {{ $run->feedback ?: 'None' }}</p>
<h3>Provider attempts</h3><ul id="generation-attempts">@forelse($attempts as $attempt)<li>{{ $attempt->provider }} · {{ $attempt->model }} · {{ $attempt->status }} · {{ $attempt->input_tokens ?? 'unknown' }} input / {{ $attempt->output_tokens ?? 'unknown' }} output tokens · Request {{ $attempt->external_id ?? 'not supplied' }}</li>@empty<li>No provider attempts yet.</li>@endforelse</ul>
</section>
<script src="{{ asset('generation.js') }}" defer></script>
@endsection
