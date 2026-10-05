@extends('layouts.app')
@section('title', 'Review document extraction')
@section('content')
<a class="back" href="{{ route('onboarding.show', ['id'=>$file->enrolment_id,'step'=>'assessment']) }}">Back to assessment findings</a>
<h1>Review document extraction</h1>
<p>{{ $file->original_name }} · Scan: {{ $file->scan_status }}</p>
<p class="notice">Extracted text is untrusted draft material. Compare it with the original, correct errors and select only relevant findings. Nothing is confirmed or sent to AI by extraction alone.</p>
@if($file->scan_status==='clean')<a href="{{ route('files.download',$file->id) }}">Download original for comparison</a>@endif
@if(!$editable)<p class="notice">This enrolment has started. Its onboarding findings cannot be replaced through this workflow.</p>@endif
@if($editable && (!$run || $run->status==='failed') && in_array($file->scan_status,['clean','quarantined','scanning']))
<form method="post" action="{{ route('documents.request',$file->id) }}">@csrf<button class="primary">{{ $run ? 'Retry extraction' : 'Request extraction' }}</button></form>
@endif
@if($run)
<section class="panel"><h2>Extraction status: {{ str_replace('_',' ',$run->status) }}</h2>
<p>Attempt {{ $run->attempt }} · Revision {{ $run->version }} @if($run->engine) · {{ $run->engine }} @endif</p>
@if(in_array($run->status,['waiting_scan','queued','running']))<p role="status">{{ $run->status==='waiting_scan' ? 'Waiting for malware scanning. A missing scanner keeps this file quarantined.' : 'The extraction worker is processing this request.' }} <a href="{{ route('documents.show',$file->id) }}">Refresh status</a></p>@endif
@if($run->status==='failed')<p role="alert">{{ App\Services\DocumentExtractionService::ERRORS[$run->error_code] ?? 'Extraction failed. Enter findings manually or supply a supported document.' }}</p><p><a href="{{ route('onboarding.show',['id'=>$file->enrolment_id,'step'=>'assessment']) }}">Use manual assessment entry</a>. Do not bypass quarantine to open a rejected file.</p>@endif
@foreach($run->warnings ?? [] as $warning)<p class="notice">{{ $warning }}</p>@endforeach
@if($run->extracted_text !== null && $file->scan_status==='clean')<details open><summary>Original extracted text (read-only)</summary><pre class="preserve-lines" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $run->extracted_text }}</pre></details>@endif
@if($run->status==='confirmed')<p>Confirmed into onboarding record {{ $run->confirmed_onboarding_id }}. Later manual edits require a new confirmation; this receipt preserves the earlier decision.</p>@endif
</section>
@if($editable && $run->status==='ready' && $file->scan_status==='clean')
<section class="panel"><h2>Correct and confirm relevant findings</h2>
<p>Summarise the relevant sections in at most 4,000 characters. Preserve the reported scale and distinguish observed evidence, provider scores and self reports. Source file, hash and extraction revision are attached automatically.</p>
<form method="post" action="{{ route('documents.review',$run->id) }}" data-preserve-input>@csrf
<input type="hidden" name="version" value="{{ $run->version }}"><input type="hidden" name="onboarding_version" value="{{ $draft?->version ?? 0 }}">
@foreach(App\Services\Onboarding::FIELDS['assessment'] as $field=>$label)
@continue($field==='source')
<label for="review-{{ $field }}">{{ $label }}</label>
@if($field==='date')<input id="review-date" name="data[date]" type="date" value="{{ old('data.date',$review['date'] ?? '') }}">
@elseif($field==='kind')<select id="review-kind" name="data[kind]"><option value="">Choose evidence type</option>@foreach(['provider score','self report','observed evidence'] as $kind)<option @selected(old('data.kind',$review['kind'] ?? '')===$kind)>{{ $kind }}</option>@endforeach</select>
@else<textarea id="review-{{ $field }}" name="data[{{ $field }}]" rows="{{ $field==='findings' ? 10 : 2 }}" maxlength="4000">{{ old('data.'.$field,$review[$field] ?? '') }}</textarea>@endif
@endforeach
<div class="form-actions"><button class="secondary" name="action" value="save">Save review draft</button><button class="primary" name="action" value="confirm">Confirm reviewed findings</button></div><p class="save-status" role="status"></p>
</form></section><script src="{{ asset('learning.js') }}" defer></script>
@endif
<details><summary>Extraction attempts</summary><ul>@foreach($runs as $entry)<li>Attempt {{ $entry->attempt }} · {{ str_replace('_',' ',$entry->status) }} · {{ $entry->created_at }}</li>@endforeach</ul></details>
@endif
@endsection
