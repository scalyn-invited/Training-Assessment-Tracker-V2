@extends('layouts.app')
@section('title', 'Overview')
@section('content')
<div class="page-heading workspace-welcome"><div><p class="eyebrow">YOUR DEVELOPMENT SPACE</p><h1>Welcome back, {{ trim(explode('·', auth()->user()->name)[0]) }}<span class="welcome-period">.</span></h1><p class="lead">A little focus today. A meaningful step forward.</p></div><span class="signed-in"><span class="dot"></span> Signed in</span></div>
<section class="workspace-hero" aria-labelledby="chapter-heading">
    <div><p class="eyebrow">THE BIG PICTURE</p><h2 id="chapter-heading">Your next chapter<br>starts with a baseline.</h2><p>Understand your starting point, practise with purpose,<br class="hero-break"> and make your progress visible.</p></div>
    <div class="growth-orbit" aria-hidden="true"><span>↗</span></div>
</section>
<div class="workspace-summary">
    <section class="panel account-panel" aria-labelledby="account-heading"><div class="section-heading"><h2 id="account-heading">Your account</h2><span class="subtle">{{ ucfirst(auth()->user()->role) }} workspace</span></div>
        <dl><div><dt>Name</dt><dd>{{ auth()->user()->name }}</dd></div><div><dt>Role</dt><dd>{{ ucfirst(auth()->user()->role) }}</dd></div><div><dt>Learning plans</dt><dd>{{ $enrolments->total() }} {{ $enrolments->total() === 1 ? 'plan' : 'plans' }}</dd></div><div><dt>Workspace scope</dt><dd>{{ auth()->user()->role === 'member' ? 'Your assigned programmes' : 'Programmes in your scope' }}</dd></div></dl>
        <a class="workspace-text-link" href="#learning-plans">View learning plans <x-icon name="arrow"/></a>
    </section>
    <section class="panel journey-panel" aria-labelledby="journey-heading"><div class="section-heading"><h2 id="journey-heading">A focused journey</h2><span>01 — 03</span></div><ol class="journey-steps"><li><span>01</span><div><h3>Know your starting point</h3><p>Capture a baseline for your chosen skills.</p></div></li><li><span>02</span><div><h3>Put learning into practice</h3><p>Turn weekly objectives into evidence.</p></div></li><li><span>03</span><div><h3>Reflect on your growth</h3><p>Review your progress with approved evidence.</p></div></li></ol></section>
</div>
<div class="section-heading" id="learning-plans"><h2><x-icon name="book"/>{{ auth()->user()->role === 'member' ? 'Your learning plans' : 'Enrolments in your scope' }}</h2><span>{{ $enrolments->total() }} {{ $enrolments->total() === 1 ? 'plan' : 'plans' }}</span></div>
<div class="cards">
@forelse($enrolments as $enrolment)
<a class="plan-card" href="{{ route('enrolments.show', $enrolment->id) }}"><div class="card-top"><span class="badge">{{ $enrolment->group->name }}</span><span class="subtle">{{ ucfirst(str_replace('_', ' ', $enrolment->status)) }}</span></div>
<h3>{{ $enrolment->title }}</h3><p>{{ $enrolment->member->name }}</p><div class="card-bottom"><span>{{ $enrolment->duration_weeks }} weeks · {{ $enrolment->daily_minutes }} min/day</span><span>View plan <x-icon name="arrow"/></span></div></a>
@empty<div class="panel empty-state"><x-icon name="book"/><h3>No enrolments available</h3><p>Your assigned learning plans will appear here.</p></div>@endforelse
</div>
{{ $enrolments->links() }}
<livewire:job-receipts />
@endsection
