@extends('layouts.app')
@section('title', $enrolment->title)
@section('content')
<a class="back" href="{{ route('dashboard') }}">← Workspace</a>
<div class="form-actions">
<a class="primary" href="{{ route('delivery.show',$enrolment->id) }}">Today and learning plan</a>
@if(auth()->id() === $enrolment->member_id || (auth()->user()->role === 'coordinator' && auth()->user()->sensitive_access))
<a class="primary" href="{{ route('onboarding.show', $enrolment->id) }}">Onboarding and plan setup</a>
@endif
@if(App\Models\ProgrammeVersion::where('enrolment_id', $enrolment->id)->exists())<a href="{{ route('plans.show', $enrolment->id) }}">View curriculum and versions</a>@endif
</div>
<div class="page-heading"><div><p class="eyebrow">{{ $enrolment->group->name }}</p><h1>{{ $enrolment->title }}</h1><p class="lead">{{ $enrolment->member->name }} · Coordinator: {{ $enrolment->coordinator->name }}</p></div><span class="badge">{{ ucfirst($enrolment->status) }}</span></div>
<div class="detail-grid"><section class="panel"><h2>Plan foundation</h2><dl><div><dt>Duration</dt><dd>{{ $enrolment->duration_weeks }} weeks</dd></div><div><dt>Daily capacity</dt><dd>{{ $enrolment->daily_minutes }} minutes</dd></div><div><dt>Calendar timezone</dt><dd>{{ $enrolment->timezone }}</dd></div><div><dt>Version</dt><dd>{{ $enrolment->version }}</dd></div><div><dt>Primary-platform sync</dt><dd>{{ str_replace('_', ' ', $enrolment->member->sync_policy) }}</dd></div></dl><div class="notice">Open Today and learning plan for lessons, saved work and reviewed results. Provisional AI output requires a coordinator decision.</div>
<h3>Check background processing</h3><p>Queue a local check to verify that your access is rechecked before a receipt is recorded. No email is sent.</p>
<form method="post" action="{{ route('enrolments.check', $enrolment->id) }}">@csrf<input type="hidden" name="expected_version" value="{{ $enrolment->version }}"><input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><button class="primary">Queue foundation check</button></form>
</section>
<section class="panel"><h2>Private evidence</h2><p>Downloads require current enrolment access. Sensitive assessments require an additional permission.</p>
<ul class="file-list">@forelse($files as $file)<li><div><strong>{{ $file->original_name }}</strong><small>{{ number_format($file->bytes / 1024, 1) }} KB · {{ $file->scan_status }}</small></div>@if($file->scan_status === 'clean')<a href="{{ route('files.download', $file->id) }}">Download</a>@else<span class="badge">Quarantined</span>@endif@if($file->purpose === 'assessment' && auth()->user()->role === 'coordinator' && auth()->id() === $enrolment->coordinator_id && auth()->user()->sensitive_access)<a href="{{ route('documents.show',$file->id) }}">Extract and review</a>@endif</li>@empty<li>No evidence visible in your scope.</li>@endforelse</ul>
@if(auth()->id() === $enrolment->member_id || (auth()->user()->role === 'coordinator' && auth()->user()->sensitive_access))
<h3>Upload for quarantine</h3><p>PDF, PNG, JPEG, TXT or inspected DOCX, up to 20 MiB. Files stay blocked until a configured scanner approves them. DOCX also requires the host's PHP zip extension.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('files.upload', $enrolment->id) }}">@csrf<label>File purpose<select name="purpose"><option value="assessment">Sensitive assessment for coordinator review</option><option value="evidence">My submitted work or supporting evidence</option></select></label><label for="evidence">Evidence file</label><input id="evidence" name="evidence" type="file" accept=".pdf,.png,.jpg,.jpeg,.txt,.docx" required><button class="secondary">Store privately</button></form>
@endif
</section></div>
<livewire:job-receipts />
@endsection
