@extends('layouts.app')
@section('title', 'Assessments')
@section('content')
<link rel="stylesheet" href="{{ asset('assessments.css') }}"><div class="assessment-workspace">
<div class="page-heading"><div><p class="eyebrow">Understand your starting point</p><h1>Assessments</h1><p>Complete assigned work and review evidence before planning the next step.</p></div></div>
@if($author)
<section class="panel"><h2>Assessment library</h2><p><a class="button" href="{{ route('assessments.new') }}">Create assessment</a></p>
@forelse($definitions as $d)
<article class="panel"><h3><a href="{{ route('assessments.edit', $d->id) }}">{{ $d->title }}</a></h3><p>Revision {{ $d->revision }} · {{ ucfirst($d->status) }}</p>
@if($d->status === 'published')
<form method="post" action="{{ route('assessments.definition') }}">@csrf<input type="hidden" name="id" value="{{ $d->id }}"><input type="hidden" name="expected_version" value="{{ $d->version }}"><input type="hidden" name="action" value="assign">
<label for="member-{{ $d->id }}">Assign to member</label><select id="member-{{ $d->id }}" name="enrolment_id" required><option value="">Choose member record</option>@foreach($enrolments as $e)<option value="{{ $e->id }}">{{ $e->member->name }} · {{ $e->id }}</option>@endforeach</select><button class="primary">Assign assessment</button></form>
@endif</article>
@empty<p>No assessment definitions yet. Create questions and a rubric, then publish the version you want to assign.</p>@endforelse
{{ $definitions->links() }}
@if($enrolments->isEmpty())<p>No eligible member records are available. A member needs an enrolment, a primary coordinator, and the coordinator's sensitive-assessment grant before assignment.</p>@endif
</section>@endif
<section class="panel"><h2>Assigned assessments</h2>
@forelse($assignments as $a)<article class="panel"><h3><a href="{{ route('assessments.show', $a->id) }}">{{ $a->definition->title }}</a></h3><p>{{ $a->enrolment->member->name }} · {{ str_replace('_', ' ', ucfirst($a->status)) }}</p></article>
@empty<p>No assessments are available for your current client and permissions.</p>@endforelse
{{ $assignments->links() }}</section>
@if($appeals->isNotEmpty())<section class="panel"><h2>Assign alternate reviewers</h2><p>Only a different eligible team coordinator can resolve an appeal. Assignment grants access to that case only.</p>
@foreach($appeals as $appeal)<form method="post" action="{{ route('assessments.appeal') }}">@csrf<input type="hidden" name="appeal_id" value="{{ $appeal->id }}"><p>Case {{ $appeal->id }}</p><label for="reviewer-{{ $appeal->id }}">Reviewer</label><select id="reviewer-{{ $appeal->id }}" name="reviewer_id">@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>@endforeach</select><label for="reason-{{ $appeal->id }}">Assignment reason</label><textarea id="reason-{{ $appeal->id }}" name="reason" required minlength="8"></textarea><button class="primary">Assign alternate reviewer</button></form>@endforeach</section>@endif
</div>
@endsection
