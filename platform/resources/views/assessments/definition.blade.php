@extends('layouts.app')
@section('title', 'Assessment definition')
@section('content')
<link rel="stylesheet" href="{{ asset('assessments.css') }}"><div class="assessment-workspace">
<p><a href="{{ route('assessments.index') }}">Back to assessments</a></p><h1>{{ $definition ? $definition->title : 'Create assessment' }}</h1>
<p>Publish a reviewed set of questions and scoring criteria. Editing a published definition creates a new draft revision; existing assignments keep their original version.</p>
<form class="panel" method="post" action="{{ route('assessments.definition') }}">@csrf<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="{{ $definition?->id }}"><input type="hidden" name="expected_version" value="{{ $definition?->version ?? 0 }}">
<label for="title">Assessment title</label><input id="title" name="title" maxlength="180" value="{{ old('title', $definition?->title) }}" required>
<h2>Questions</h2><p>Leave unused rows empty. Each question has a stable reference.</p>
@php($questions = old('questions', $definition?->questions ?? []))
@for($i=0; $i < max(3, count($questions) + 1); $i++)<fieldset><legend>Question {{ $i+1 }}</legend><label for="qid-{{ $i }}">Reference</label><input id="qid-{{ $i }}" name="questions[{{ $i }}][id]" value="{{ $questions[$i]['id'] ?? 'q'.($i+1) }}"><label for="prompt-{{ $i }}">Question</label><textarea id="prompt-{{ $i }}" name="questions[{{ $i }}][prompt]" maxlength="4000">{{ $questions[$i]['prompt'] ?? '' }}</textarea></fieldset>@endfor
<h2>Rubric</h2><p>Scores use 0–100. Weights must total 1; for example, two equally weighted criteria use 0.5 each. Missing evidence produces an unavailable score, not zero.</p>
@php($criteria = old('criteria', $definition?->rubric['criteria'] ?? []))
@for($i=0; $i < max(3, count($criteria) + 1); $i++)<fieldset><legend>Criterion {{ $i+1 }}</legend>
<label for="cid-{{ $i }}">Reference</label><input id="cid-{{ $i }}" name="criteria[{{ $i }}][id]" value="{{ $criteria[$i]['id'] ?? 'c'.($i+1) }}">
<label for="category-{{ $i }}">Category</label><input id="category-{{ $i }}" name="criteria[{{ $i }}][category]" value="{{ $criteria[$i]['category'] ?? '' }}">
<label for="label-{{ $i }}">Criterion</label><input id="label-{{ $i }}" name="criteria[{{ $i }}][label]" value="{{ $criteria[$i]['label'] ?? '' }}">
<label for="guidance-{{ $i }}">Scoring guidance</label><textarea id="guidance-{{ $i }}" name="criteria[{{ $i }}][guidance]">{{ $criteria[$i]['guidance'] ?? '' }}</textarea>
<label for="weight-{{ $i }}">Weight</label><input id="weight-{{ $i }}" name="criteria[{{ $i }}][weight]" type="number" min="0.000001" max="1" step="0.000001" value="{{ $criteria[$i]['weight'] ?? '' }}"></fieldset>@endfor
<label for="threshold">Success threshold (optional, 0–100)</label><input id="threshold" name="threshold" type="number" min="0" max="100" step="0.001" value="{{ old('threshold', $definition?->rubric['threshold'] ?? '') }}"><p>Leave blank when no success threshold has been approved.</p>
<button class="primary">{{ $definition?->status === 'published' ? 'Create revised draft' : 'Save draft' }}</button></form>
@if($definition?->status === 'draft')<form class="panel" method="post" action="{{ route('assessments.definition') }}">@csrf<input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="{{ $definition->id }}"><input type="hidden" name="expected_version" value="{{ $definition->version }}"><h2>Publish reviewed definition</h2><p>Publishing freezes this version for member assignment.</p><button class="primary">Publish assessment</button></form>@endif
</div>
@endsection
