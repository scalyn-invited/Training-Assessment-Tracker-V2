@extends('layouts.app')
@section('title', 'Platform administration')
@section('content')
<header class="page-header"><div><p class="eyebrow">Platform</p><h1>Platform administration</h1><p>Manage organisations and access. Training content requires its own permissions.</p></div></header>
<nav class="panel" aria-label="Platform sections">
@foreach(['overview'=>'Overview','clients'=>'Clients','admins'=>'Global Admins','appearance'=>'Platform appearance','audit'=>'Administrative audit'] as $key=>$label)
<a class="button {{ $section===$key ? 'primary' : 'secondary' }}" href="{{ $key==='overview' ? route('platform.overview') : route('platform.section',$key) }}" @if($section===$key) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
@include('local-accounts.admin-panel')
@if($section==='overview')
<section class="panel"><h2>System overview</h2><p>{{ $clients->total() }} clients · {{ $admins->total() }} current Global Admin assignments</p><dl><dt>Identity connection</dt><dd>{{ app(\App\Services\LocalAccounts::class)->enabled() ? 'Password sign-in' : (config('training.mock_identity') ? 'Local mock sign-in' : 'External identity configuration required') }}</dd><dt>External email delivery</dt><dd>{{ config('training.smtp_enabled') ? 'Configured; delivery not verified here' : 'Email is not configured' }}</dd><dt>Live AI</dt><dd>{{ config('ai.live_enabled') ? 'Configured; provider access not verified here' : 'AI generation is not configured' }}</dd></dl><p>Operational credentials and provider configuration are not editable here.</p></section>
@elseif($section==='clients')
<section class="panel"><h2>Create client</h2><form method="post" action="{{ route('platform.act') }}">@csrf<input type="hidden" name="action" value="create"><input type="hidden" name="version" value="0"><label>Directory name<input name="name" required maxlength="160"></label><label>Creation reason<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary">Create client</button></form></section>
<section class="panel"><h2>Clients</h2>
@forelse($clients as $client)
@php
    $state = $states->get($client->id);
@endphp
<details><summary>{{ $client->name }} · {{ $state?->suspended ? 'Suspended' : ($client->active ? 'Active' : 'Directory inactive') }}</summary>
<p>Client Admin assignments: {{ $clientAdmins->get($client->id,collect())->pluck('name')->join(', ') ?: 'None' }}. Suspended clients cannot use these assignments until reactivated.</p>
<form method="post" action="{{ route('platform.act') }}">@csrf<input type="hidden" name="client" value="{{ $client->id }}"><input type="hidden" name="version" value="{{ $state->version ?? 0 }}"><label>Directory name<input name="name" value="{{ $client->name }}" required maxlength="160"></label><label>Client change reason<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary" name="action" value="edit">Save directory name</button> <button class="secondary" name="action" value="{{ $state?->suspended ? 'reactivate' : 'suspend' }}">{{ $state?->suspended ? 'Reactivate client' : 'Suspend client' }}</button></form>
<p>Suspension blocks access and cancels pending work. Reactivation restores ordinary access; sensitive grants and appeal assignments need fresh approval.</p></details>
@empty<p>No clients yet.</p>@endforelse
{{ $clients->links() }}</section>
@endif
@if(in_array($section,['clients','admins']))
@if($section==='admins')<section class="panel"><h2>Global Admins</h2>@forelse($admins as $admin)<form method="post" action="{{ route('platform.act') }}">@csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="version" value="0"><input type="hidden" name="grant" value="{{ $admin->id }}"><h3>{{ $admin->name }}</h3><p>{{ $admin->email }} · {{ $admin->active ? 'Active account' : 'Inactive account' }}</p><label>Revocation reason<input name="reason" required minlength="8" maxlength="2000"></label><button class="secondary">Revoke Global Admin</button></form>@empty<p>No Global Admins.</p>@endforelse{{ $admins->links() }}<p>The last active Global Admin cannot be removed.</p></section>@endif
<section class="panel"><h2>Existing accounts</h2><form method="get"><label>Search account name<input name="search" value="{{ request('search') }}" maxlength="120"></label><button class="secondary">Search</button></form>
@forelse($accounts as $account)
@php
    $accountForm = 'platform-account-'.$account->id;
    $retryAccount = old('_form') === $accountForm;
    $memberships = $accountClients->get($account->id, collect())->pluck('organisation_id')->unique();
    $selectedClient = $retryAccount ? old('client') : ($memberships->count() === 1 ? $memberships->first() : '');
@endphp
<details @if($retryAccount) open @endif><summary>{{ $account->name }} · {{ $account->email }}</summary><form method="post" action="{{ route('platform.act') }}">@csrf
<input type="hidden" name="_form" value="{{ $accountForm }}"><input type="hidden" name="user" value="{{ $account->id }}"><input type="hidden" name="version" value="{{ $account->permission_version }}">
<input type="hidden" name="_accounts_page" value="{{ $accounts->currentPage() }}"><input type="hidden" name="_search" value="{{ request('search') }}">
@if($section==='clients')
<label>Client<select name="client" required><option value="">Choose client…</option>@foreach($clientChoices as $client)<option value="{{ $client->id }}" @selected($selectedClient === $client->id)>{{ $client->name }}</option>@endforeach</select></label>
<p>Client Admin access requires active membership in the selected client.</p>
<label>Action<select name="action" required><option value="">Choose action…</option>@unless(app(\App\Services\LocalAccounts::class)->enabled())<option value="join" @selected($retryAccount && old('action') === 'join')>Explicitly join client</option>@endunless<option value="grant" @selected($retryAccount && old('action') === 'grant')>Grant Client Admin</option><option value="revoke" @selected($retryAccount && old('action') === 'revoke')>Revoke Client Admin</option></select></label>
@else<input type="hidden" name="action" value="appoint">@endif
<label>Access change reason<input name="reason" value="{{ $retryAccount ? old('reason') : '' }}" required minlength="8" maxlength="2000"></label><button class="primary">{{ $section==='clients' ? 'Apply client access change' : 'Grant Global Admin' }}</button></form></details>@empty<p>No matching active accounts.</p>@endforelse{{ $accounts->links() }}</section>
@elseif($section==='appearance')
<section class="panel"><h2>Platform appearance</h2><p>Shared screens and unconfigured clients inherit these defaults. Client settings and personal themes take precedence.</p>
<form method="post" id="platform-appearance-form" action="{{ route('platform.appearance') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="version" value="{{ $settings->version ?? 0 }}">
<label>Platform display name<input name="display_name" value="{{ old('display_name',$settings->display_name ?? config('branding.defaults.client_name')) }}" required maxlength="120"></label>
<label>Application name<input name="app_name" value="{{ old('app_name',$settings->app_name ?? config('branding.defaults.app_name')) }}" required maxlength="120"></label>
<label>Brand colour<input type="color" name="accent" value="{{ old('accent',$settings->accent ?? config('branding.defaults.accent_light')) }}" required></label>
<p>Choose Platform Light or Platform Dark below to apply your brand colour to buttons, navigation, backgrounds and cards. Standard and neon themes keep their own colours. Personal choices stay unchanged; choose Use platform default in the Theme menu to follow this setting.</p>
<div class="auto-theme-previews" aria-label="Platform theme previews">
@foreach($branding['platform_previews'] as $variant => $tokens)
<section class="auto-theme-preview" data-platform-preview="{{ $variant }}" aria-label="Platform {{ ucfirst($variant) }} preview" style="@foreach($tokens as $key => $value)--preview-{{ $key }}:{{ $value }};@endforeach">
<h3>Platform {{ ucfirst($variant) }}</h3><p>Preview only until you save.</p>
<div class="auto-preview-card"><span class="auto-preview-active">Active navigation</span><h4>Your platform workspace</h4><p>Readable text and comfortable cards.</p><span class="auto-preview-button">Button preview</span></div>
</section>
@endforeach
</div>
<label>Default theme<select name="default_theme">@foreach(\App\Services\Branding::THEMES + \App\Services\Branding::PLATFORM_THEMES as $key=>$label)<option value="{{ $key }}" @selected(old('default_theme',$settings->default_theme ?? config('branding.defaults.default_theme'))===$key)>{{ $label }}</option>@endforeach</select></label>
@if($settings?->logo_path)<img src="{{ route('platform-logo',['v'=>$settings->version]) }}" alt="Current platform logo" style="max-width:180px;max-height:100px;object-fit:contain;background:#eee;padding:8px"><label><input type="checkbox" name="remove_logo" value="1">Remove custom logo</label>@endif
<label>Platform logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><p>PNG, JPEG or WebP. Maximum 2 MB and 4096 × 4096 pixels. This platform logo is visible on shared sign-in screens.</p>
<label>Appearance change reason<input name="reason" required minlength="8" maxlength="2000"></label><button class="primary">Save platform appearance</button> <a class="button secondary" href="{{ route('platform.section','appearance') }}">Discard changes</a></form></section>
@elseif($section==='audit')
<section class="panel"><h2>Administrative audit</h2><form method="get"><label>Exact action<input name="action" value="{{ request('action') }}" maxlength="100"></label><label>Exact target ID<input name="target" value="{{ request('target') }}" maxlength="80"></label><button class="secondary">Filter audit</button></form>
@forelse($events as $event)<article><h3>{{ $event->action }}</h3><p>{{ $event->created_at }} · {{ $event->name ?? ('Operator: '.$event->operator_reference) }}</p><p>Target: {{ $event->target_id }}</p><p>{{ $event->reason }}</p></article>@empty<p>No administrative events match these filters.</p>@endforelse{{ $events->links() }}</section>
@endif
@endsection
