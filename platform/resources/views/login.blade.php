@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="login-grid">
    <section class="intro"><p class="eyebrow">LEARNING WITH PURPOSE</p><h1>Make progress.<br>Keep the evidence.</h1><p class="lead">One place for focused training, thoughtful review, and a clear view of what comes next.</p>
    <div class="steps"><div><b>01</b><span>Set a meaningful goal</span></div><div><b>02</b><span>Learn and practise</span></div><div><b>03</b><span>Review demonstrated skills</span></div></div>
    </section>
    <section class="panel login-panel"><p class="eyebrow">EXPLORE THE FOUNDATION</p><h2>Choose a test identity</h2><p>Each synthetic identity has a different view of the workspace. This simulates sign-in; live SSO and MFA are not connected.</p>
    @forelse($identities as $identity)
    <form method="post" action="{{ route('login.store') }}">@csrf<input type="hidden" name="identity_id" value="{{ $identity->id }}">
    <button class="identity"><span>{{ App\Models\User::find($identity->user_id)->name }}</span><span aria-hidden="true">→</span></button></form>
    @empty<div class="notice">Mock sign-in is disabled or no synthetic identities are available. Live identity configuration is pending.</div>@endforelse
    </section>
</div>
@endsection
