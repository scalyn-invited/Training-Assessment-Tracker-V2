@extends('layouts.app')
@section('title','Review queue')
@section('content')
<h1>Review queue</h1><p>Pending work and assigned appeals within your current permission scope. Provisional results require your evidence-based decision.</p>
<section class="panel directory-panel"><p class="directory-count">{{ $submissions->total() }} reviews in your scope</p><x-directory-table label="Review queue" :columns="['Member / Programme','Attempt','Status','Submitted','Actions']">@forelse($submissions as $submission)<tr><td><strong>{{ $submission->enrolment->member->name }}</strong><small>{{ $submission->enrolment->title }}</small></td><td>{{ $submission->attempt }}</td><td><span class="directory-badge">{{ str_replace('_',' ',$submission->status) }}</span></td><td>{{ $submission->created_at }} UTC</td><td><a class="directory-action" href="{{ route('submission.show',$submission->id) }}">Review submission &rarr;</a></td></tr>@empty<tr><td colspan="5">No pending reviews in your scope.</td></tr>@endforelse</x-directory-table>{{ $submissions->links() }}</section>
@endsection
