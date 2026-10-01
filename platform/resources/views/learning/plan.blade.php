@extends('layouts.app')
@section('title', 'Curriculum editor')
@section('content')
<a class="back" href="{{ route('enrolments.show', $enrolment->id) }}">← Enrolment</a>
<div class="page-heading"><div><p class="eyebrow">Curriculum · Version {{ $plan->version }}</p><h1>{{ $enrolment->title }}</h1><p>{{ $enrolment->member->name }} · {{ $plan->calendar->timezone }}</p></div><span class="badge">{{ str_replace('_',' ',ucfirst($plan->state)) }}</span></div>
<p class="notice">{{ $plan->state === 'approved' ? 'This exact baseline is approved and reserves shared capacity. Training activation and submissions come in a later milestone.' : 'Preview only. Draft content is not approved learning material and does not reserve capacity.' }}</p>
<div class="form-actions"><a href="{{ route('onboarding.show', ['id'=>$enrolment->id,'step'=>'review']) }}">Review onboarding or reschedule</a>
<details><summary>Version history</summary><ul>@foreach($versions as $version)<li><a href="{{ route('plans.show', ['id'=>$enrolment->id,'version'=>$version->version]) }}">Version {{ $version->version }} · {{ str_replace('_',' ',$version->state) }}</a> · {{ $version->reason }}</li>@endforeach</ul></details></div>
<nav class="step-nav" aria-label="Learning blocks">@foreach($plan->content['blocks'] as $i=>$item)<a href="{{ route('plans.show', ['id'=>$enrolment->id,'version'=>$plan->version,'block'=>$i]) }}" @if($i === $blockIndex) aria-current="page" @endif>Block {{ $i+1 }} · {{ $item['start'] }}</a>@endforeach</nav>
<section class="panel">
@if($editable)
<details class="lesson"><summary>Generate or revise with AI</summary>
<p>Generate all blocks as a new draft using the administrator-approved route. The local mock produces synthetic demonstration lessons; it does not tailor content or apply revision feedback. Every result still requires coordinator review.</p>
<p>Data used: profile, confirmed findings, target competencies, KPI definitions, reviewed resource, scheduled dates, existing draft and your revision notes. Names, identity records and uploaded files are excluded. The local mock sends no network requests.</p>
<form method="post" action="{{ route('ai.request', $enrolment->id) }}">@csrf
<input type="hidden" name="expected_version" value="{{ $plan->version }}"><input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
<label>Revision notes (optional)<textarea name="feedback" rows="3" maxlength="4000"></textarea></label>
<button class="primary">Queue curriculum generation</button></form>
@php($recentRuns = \App\Models\AiRun::where('enrolment_id', $enrolment->id)->latest()->limit(5)->get())
@if($recentRuns->isNotEmpty())<h3>Recent generation requests</h3><ul>@foreach($recentRuns as $recentRun)<li><a href="{{ route('ai.show', $recentRun->id) }}">{{ $recentRun->created_at }} · {{ $recentRun->status }}</a></li>@endforeach</ul>@endif
</details>
@endif
<h2>Block {{ $blockIndex+1 }} · {{ $block['start'] }}</h2>
<p class="subtle">Future adaptation cutoff: {{ Carbon\CarbonImmutable::parse($block['freeze_at'])->setTimezone($plan->calendar->timezone)->format('d M Y, H:i T') }}. Calendar version {{ $plan->calendar->version }}. Baseline approval does not activate training.</p>
<details><summary>Confirmed competency, KPI and resource references</summary>
<h3>Competencies</h3><ol>@foreach(preg_split('/\R/', trim($plan->onboarding->data['capability']['competencies']), -1, PREG_SPLIT_NO_EMPTY) as $competency)<li>{{ $competency }}</li>@endforeach</ol>
<h3>Measures</h3><ol>@foreach($plan->onboarding->data['measures']['kpis'] as $kpi)<li>{{ $kpi['name'] }} · {{ $kpi['target'] }} {{ $kpi['unit'] }} · {{ $kpi['evidence'] }}</li>@endforeach</ol>
<h3>Approved resource catalogue</h3><p>{{ $plan->onboarding->data['resources']['title'] }} · {{ $plan->onboarding->data['resources']['reference'] }}</p><p>Permission: {{ $plan->onboarding->data['resources']['licence'] }} · Cost: {{ $plan->onboarding->data['resources']['cost'] }} · Access: {{ $plan->onboarding->data['resources']['access'] }}</p><p>Each lesson uses this reviewed resource; changes require a new onboarding revision and refreshed draft.</p>
</details>
<form method="post" action="{{ route('plans.edit', $enrolment->id) }}" @if($editable) data-preserve-input @endif>
@csrf<input type="hidden" name="expected_version" value="{{ $plan->version }}"><input type="hidden" name="block" value="{{ $blockIndex }}">
<fieldset @disabled(!$editable)><legend class="sr-only">Block content</legend>
<label for="objective">Learning objective</label><textarea id="objective" name="data[objective]" rows="3" maxlength="4000">{{ $block['objective'] }}</textarea>
<label for="prerequisites">Prerequisites and sequence</label><textarea id="prerequisites" name="data[prerequisites]" rows="2" maxlength="4000">{{ $block['prerequisites'] }}</textarea>
<label>Target competency number<input type="number" name="data[competency]" min="1" value="{{ $block['competency'] }}"></label>
<fieldset class="lesson"><legend>Assessment rubric</legend><p>Score range 0–100; weights must total 1. Criteria reference this block’s selected competency and the evidence required by lesson completion criteria.</p>
<div class="form-grid">@foreach(['one','two'] as $key)<label>Criterion {{ $key }} and evidence expectations<textarea name="data[criterion_{{ $key }}]" rows="3" maxlength="2000">{{ $block['criterion_'.$key] }}</textarea></label><label>Weight {{ $key }}<input type="number" step="0.01" min="0" max="1" name="data[weight_{{ $key }}]" value="{{ $block['weight_'.$key] }}"></label>@endforeach</div></fieldset>
@foreach($block['lessons'] as $i=>$lesson)
<details class="lesson" @if($loop->first) open @endif><summary>Day {{ $i+1 }} · {{ $lesson['date'] }} · {{ $lesson['title'] ?? 'Lesson content needed' }}</summary>
@foreach(['title'=>'Lesson title','explanation'=>'Explanation and learning content','example'=>'Worked example or approved demonstration','activity'=>'Practical activity and deliverable','tools'=>'Permitted tools','completion'=>'Completion criteria and evidence required'] as $key=>$label)<label for="lesson-{{ $i }}-{{ $key }}">{{ $label }}</label><textarea id="lesson-{{ $i }}-{{ $key }}" name="data[lessons][{{ $i }}][{{ $key }}]" rows="{{ in_array($key,['explanation','example','activity']) ? 4 : 2 }}" maxlength="8000">{{ $lesson[$key] ?? '' }}</textarea>@endforeach
<div class="form-grid">@foreach(['reading','practice','assessment','revision'] as $key)<label>{{ ucfirst($key) }} minutes<input name="data[lessons][{{ $i }}][{{ $key }}]" type="number" min="0" max="120" value="{{ $lesson[$key] ?? 0 }}"></label>@endforeach
<label>Linked KPI number<input type="number" min="1" max="{{ count($plan->onboarding->data['measures']['kpis']) }}" name="data[lessons][{{ $i }}][kpi]" value="{{ $lesson['kpi'] ?? 1 }}"></label></div>
<p class="subtle">Combined time must be 15–{{ $plan->onboarding->data['schedule']['daily_minutes'] }} minutes, including assessment and revision.</p>
</details>
@endforeach
@if($editable)<label>Reason for this version<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary">Save new draft version</button><p class="save-status" role="status"></p>@endif
</fieldset></form>
</section>
@if($previous && isset($previous->content['blocks'][$blockIndex]))
@php($oldBlock = $previous->content['blocks'][$blockIndex])
<details class="panel comparison"><summary>Compare this block with version {{ $previous->version }}</summary>
@if($oldBlock === $block)<p>No content changes in this block.</p>@else
@foreach(['objective','prerequisites','competency','criterion_one','criterion_two','weight_one','weight_two'] as $field)
@if(($oldBlock[$field] ?? '') !== ($block[$field] ?? ''))<h3>{{ ucfirst(str_replace('_',' ',$field)) }}</h3><div class="form-grid"><p><strong>Previous</strong><br>{{ $oldBlock[$field] ?? 'Not set' }}</p><p><strong>Current</strong><br>{{ $block[$field] ?? 'Not set' }}</p></div>@endif
@endforeach
@foreach($block['lessons'] as $index=>$lesson)
@foreach($lesson as $field=>$value)
@if(($oldBlock['lessons'][$index][$field] ?? '') !== $value)<h3>Day {{ $index+1 }} · {{ ucfirst($field) }}</h3><div class="form-grid"><p><strong>Previous</strong><br>{{ $oldBlock['lessons'][$index][$field] ?? 'Not set' }}</p><p><strong>Current</strong><br>{{ $value }}</p></div>@endif
@endforeach
@endforeach
@endif</details>
@endif
@if($editable && in_array($plan->state,['draft','needs_review']))
<section class="panel receipts"><h2>Review the complete baseline</h2><p>All {{ count($plan->content['blocks']) }} blocks need usable lessons, rubric weights and valid time budgets. Approval rechecks current permissions, input versions and shared capacity in one transaction.</p>
<form method="post" action="{{ route('plans.review', $enrolment->id) }}" data-preserve-input>@csrf<input type="hidden" name="expected_version" value="{{ $plan->version }}">
@if($plan->state === 'needs_review')<input type="hidden" name="action" value="approve"><label>Approval rationale<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary">Approve exact version {{ $plan->version }}</button>@else<input type="hidden" name="action" value="submit"><button class="primary">Validate all blocks and request review</button>@endif
<p class="save-status" role="status"></p></form></section>
@endif
<script src="{{ asset('learning.js') }}" defer></script>
@endsection
