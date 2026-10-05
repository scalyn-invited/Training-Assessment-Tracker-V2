@extends('layouts.app')
@section('title', 'Onboarding')
@section('content')
<a class="back" href="{{ route('enrolments.show', $enrolment->id) }}">← Enrolment</a>
<div class="page-heading"><div><p class="eyebrow">Learning setup · Revision {{ $draft?->version ?? 0 }}</p><h1>{{ $enrolment->member->name }}</h1><p>Coordinator: {{ $enrolment->coordinator->name }} · {{ $enrolment->group->name }}</p></div><span class="badge">Saved draft ≠ approved plan</span></div>
<nav class="step-nav" aria-label="Onboarding steps">
@foreach(App\Services\Onboarding::STEPS as $key => $label)<a href="{{ route('onboarding.show', ['id' => $enrolment->id, 'step' => $key]) }}" @if($step === $key) aria-current="step" @endif>{{ $loop->iteration }}. {{ $label }} @if(isset($data[$key]))<span class="subtle">· saved</span>@endif</a>@endforeach
<a href="{{ route('onboarding.show', ['id' => $enrolment->id, 'step' => 'review']) }}" @if($step === 'review') aria-current="step" @endif>7. Review and create</a></nav>
@if($step !== 'review')
<section class="panel">
<h2>{{ App\Services\Onboarding::STEPS[$step] }}</h2>
@if(!$editable)<p class="notice">Your coordinator owns these inputs. You can review the saved information here.</p>@endif
@if($step === 'assessment')<p class="notice">Enter a manual summary, or have the coordinator extract and review an uploaded assessment. Files stay quarantined until scanning succeeds. Every edit requires coordinator confirmation.</p>
@if($coordinator)<p><a href="{{ route('enrolments.show',$enrolment->id) }}">Upload or select a private assessment for extraction</a></p>@endif<p id="assessment-confirmation" class="subtle">{{ !empty($data['assessment']['confirmed_by']) ? 'Confirmed by coordinator for the saved assessment.' : 'Assessment confirmation pending.' }}</p>@endif
<form method="post" action="{{ route('onboarding.save', ['id' => $enrolment->id, 'step' => $step]) }}" @if($editable) data-autosave @endif>
@csrf<input type="hidden" name="expected_version" value="{{ $draft?->version ?? 0 }}">
<fieldset @disabled(!$editable)><legend class="sr-only">{{ App\Services\Onboarding::STEPS[$step] }} inputs</legend>
@if(isset(App\Services\Onboarding::FIELDS[$step]))
@foreach(App\Services\Onboarding::FIELDS[$step] as $field => $label)
<label for="field-{{ $field }}">{{ $label }}</label>
@if($field === 'date')<input id="field-{{ $field }}" name="data[{{ $field }}]" type="date" value="{{ $data[$step][$field] ?? '' }}">
@elseif($field === 'kind')<select id="field-kind" name="data[kind]"><option value="">Choose evidence type</option>@foreach(['provider score', 'self report', 'observed evidence'] as $kind)<option @selected(($data[$step]['kind'] ?? '') === $kind)>{{ $kind }}</option>@endforeach</select>
@else<textarea id="field-{{ $field }}" name="data[{{ $field }}]" rows="{{ in_array($field, ['findings','competencies']) ? 5 : 2 }}" maxlength="4000">{{ $data[$step][$field] ?? '' }}</textarea>@endif
@endforeach
@elseif($step === 'schedule')
<p>Propose a plan budget and a shared daily capacity across all programmes. Only the coordinator can confirm the shared calendar at the review step.</p>
<p class="subtle">Business days for review cutoffs default to Monday–Friday, excluding business holidays. Your selected training days and absence dates do not change those business weekdays.</p>
<div class="form-grid">
<div><label for="duration">Learning blocks</label><select id="duration" name="data[duration]">@foreach([4,6,8,10,12] as $weeks)<option value="{{ $weeks }}" @selected(($data['schedule']['duration'] ?? 4) == $weeks)>{{ $weeks }} blocks</option>@endforeach</select></div>
<label>Minutes per day for this plan<input name="data[daily_minutes]" type="number" min="15" max="120" value="{{ $data['schedule']['daily_minutes'] ?? 30 }}"></label>
<label>Shared daily capacity (all plans)<input name="data[capacity_minutes]" type="number" min="15" max="120" value="{{ $data['schedule']['capacity_minutes'] ?? $calendar?->daily_minutes ?? 30 }}"></label>
<label>Start date<input name="data[start_date]" type="date" value="{{ $data['schedule']['start_date'] ?? '' }}"></label>
<label>Calendar timezone<input name="data[timezone]" list="timezones" value="{{ $data['schedule']['timezone'] ?? $calendar?->timezone ?? 'Asia/Manila' }}"><datalist id="timezones">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}">@endforeach</datalist></label>
</div>
<fieldset><legend>Scheduled weekdays (default Monday–Friday)</legend><div class="choices">@foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $day => $label)<label><input type="checkbox" name="data[weekdays][]" value="{{ $day }}" @checked(in_array($day, $data['schedule']['weekdays'] ?? $calendar?->weekdays ?? [1,2,3,4,5]))> {{ $label }}</label>@endforeach</div></fieldset>
@foreach(['holidays'=>'Business holidays','absences'=>'Approved absence dates'] as $field => $label)<label for="{{ $field }}">{{ $label }} (YYYY-MM-DD, one per line)</label><textarea id="{{ $field }}" name="data[{{ $field }}]" rows="3">{{ implode("\n", $data['schedule'][$field] ?? $calendar?->$field ?? []) }}</textarea>@endforeach
<label for="constraints">Workload constraints and rescheduling notes</label><textarea id="constraints" name="data[constraints]" rows="3">{{ $data['schedule']['constraints'] ?? '' }}</textarea>
@elseif($step === 'measures')
<p>Define three primary measures. Use “unknown” for an unavailable baseline. These are planning inputs; official KPI formulas and observations come in the KPI milestone.</p>
@for($i=0;$i<3;$i++)<fieldset class="lesson"><legend>KPI {{ $i+1 }}</legend><div class="form-grid">
@foreach(['name'=>'Name','baseline'=>'Baseline (or unknown)','target'=>'Target','unit'=>'Unit','rationale'=>'Why this matters','evidence'=>'Evidence method','cadence'=>'Review cadence'] as $field=>$label)<label>{{ $label }}<input name="data[kpis][{{ $i }}][{{ $field }}]" value="{{ $data['measures']['kpis'][$i][$field] ?? '' }}" maxlength="{{ in_array($field,['rationale','evidence']) ? 1000 : ($field === 'unit' ? 100 : 200) }}"></label>@endforeach
</div></fieldset>@endfor
@endif
</fieldset>
@if($editable)<div class="form-actions"><button class="primary">Save draft</button>@if($step === 'assessment' && $coordinator)<button class="secondary" name="confirm_assessment" value="1">Confirm assessment findings</button>@endif</div><p class="save-status" role="status" aria-live="polite">Changes save automatically after a short pause. Keep this page open until “Draft saved” appears.</p>@endif
</form></section>
@else
<section class="panel"><h2>Review the saved inputs</h2><p>No data is sent to AI. Create a manual curriculum draft, then complete every lesson and approve its exact version before the plan is ready.</p>
@if($preview)<p class="notice">Budget estimate using the confirmed shared calendar: {{ $preview['days'] }} scheduled days · {{ $preview['minutes'] }} minutes ({{ number_format($preview['minutes']/60, 1) }} hours). All reading, practice, assessment and revision must fit within this budget. Creation checks that your saved availability matches the confirmed calendar.</p>@endif
@foreach(App\Services\Onboarding::STEPS as $key=>$label)<details class="review-section"><summary>{{ $label }} · {{ isset($data[$key]) ? 'saved, review completeness below' : 'not saved' }}</summary>
@if(isset($data[$key]))<dl>@foreach($data[$key] as $field=>$value)@continue(in_array($field,['confirmed_by','confirmed_at']))
<div><dt>{{ str_replace('_',' ',ucfirst($field)) }}</dt><dd>@if(is_array($value))@foreach($value as $entry)@if(is_array($entry))<p>{{ implode(' · ', $entry) }}</p>@else{{ $entry }}{{ !$loop->last ? ', ' : '' }}@endif@endforeach @else{{ $value }}@endif</dd></div>@endforeach</dl>@endif</details>@endforeach
<p>Assessment: {{ !empty($data['assessment']['confirmed_by']) ? 'coordinator confirmed' : 'confirmation required' }}.</p>
@if($calendar)<p>Shared calendar version {{ $calendar->version }} · {{ $calendar->timezone }} · {{ $calendar->daily_minutes }} minutes per available day.</p>@else<p>Shared calendar has not been confirmed.</p>@endif
@if($coordinator && $draft)
<form method="post" action="{{ route('onboarding.calendar', $enrolment->id) }}" data-preserve-input>@csrf<input type="hidden" name="expected_version" value="{{ $calendar?->version ?? 0 }}"><input type="hidden" name="onboarding_version" value="{{ $draft->version }}">
<label>Reason for confirming or changing the shared calendar<input name="reason" required minlength="8" maxlength="2000"></label><button class="secondary">Confirm shared calendar</button><p class="save-status" role="status"></p></form>
<hr>
<form method="post" action="{{ route('plans.create', $enrolment->id) }}" data-preserve-input>@csrf<input type="hidden" name="expected_version" value="{{ $plan?->version ?? 0 }}"><input type="hidden" name="onboarding_version" value="{{ $draft->version }}">
<label>Draft creation or rescheduling reason<input name="reason" required minlength="8" maxlength="2000"></label><p>Refreshing a draft uses these saved inputs and the confirmed calendar. Existing lesson content is copied by block/day position for explicit review; approved history remains preserved.</p><button class="primary">{{ $plan ? 'Create refreshed draft' : 'Create curriculum draft' }}</button><p class="save-status" role="status"></p></form>
@endif
@if($plan)<p><a href="{{ route('plans.show', $enrolment->id) }}">Open curriculum · version {{ $plan->version }}</a></p>@endif
</section>
@endif
<script src="{{ asset('learning.js') }}" defer></script>
@endsection
