@extends('layouts.app')
@section('title','Review queue')
@section('content')
<h1>Review queue</h1><p>Pending work and assigned appeals within your current permission scope. Provisional results require your evidence-based decision.</p>
<section class="panel"><ul class="file-list">@forelse($submissions as $submission)<li><div><a href="{{ route('submission.show',$submission->id) }}">{{ $submission->enrolment->member->name }} · {{ $submission->enrolment->title }}</a><small>Attempt {{ $submission->attempt }} · {{ str_replace('_',' ',$submission->status) }} · {{ $submission->created_at }}</small></div></li>@empty<li>No pending reviews in your scope.</li>@endforelse</ul>{{ $submissions->links() }}</section>
@endsection
