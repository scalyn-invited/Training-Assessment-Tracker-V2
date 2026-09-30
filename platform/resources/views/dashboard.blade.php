@extends('layouts.app')
@section('title', 'Workspace')
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ strtoupper(auth()->user()->role) }} WORKSPACE</p><h1>Your training, in focus.</h1><p class="lead">{{ auth()->user()->name }} · Only records within your access scope appear here.</p></div><span class="badge">Foundation preview</span></div>
<div class="metrics"><div class="metric"><span>Visible enrolments</span><strong>{{ $enrolments->total() }}</strong><small>Filtered before counting</small></div><div class="metric"><span>Learning stage</span><strong>Onboarding</strong><small>Content authoring comes in B03</small></div><div class="metric"><span>Review principle</span><strong>Human approval</strong><small>AI cannot publish official results</small></div></div>
<div class="section-heading"><h2>{{ auth()->user()->role === 'member' ? 'Your learning plans' : 'Enrolments in your scope' }}</h2><span>{{ now()->format('d M Y') }}</span></div>
<div class="cards">
@forelse($enrolments as $enrolment)
<a class="plan-card" href="{{ route('enrolments.show', $enrolment->id) }}"><div class="card-top"><span class="badge">{{ $enrolment->group->name }}</span><span class="subtle">Version {{ $enrolment->version }}</span></div>
<h3>{{ $enrolment->title }}</h3><p>{{ $enrolment->member->name }}</p><div class="card-bottom"><span>{{ $enrolment->duration_weeks }} weeks · {{ $enrolment->daily_minutes }} min/day</span><span>View plan →</span></div></a>
@empty<div class="panel"><h3>No enrolments available</h3><p>Your active group assignments determine what appears here.</p></div>@endforelse
</div>
{{ $enrolments->links() }}
<livewire:job-receipts />
@endsection
