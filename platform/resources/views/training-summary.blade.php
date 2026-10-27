@extends('layouts.app')
@section('title', 'Training summary')
@section('content')
<section class="panel"><h1>{{ $summary['title'] }}</h1><p>Status: {{ $summary['status'] }}</p><p>Your team access permits this training summary. Detailed records and review actions require separate authority.</p></section>
@endsection
