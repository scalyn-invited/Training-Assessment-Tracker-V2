<!DOCTYPE html>
<html lang="en" data-client-theme="{{ $branding['theme_kind'] }}" data-brand-mode="{{ $branding['mode'] }}" data-theme="{{ $branding['selected_theme'] }}" data-preference-url="{{ $branding['preference_ready'] ? route('appearance.preference') : '' }}" data-preference-client="{{ $branding['preference_client'] }}" data-preference-version="{{ $branding['preference_version'] }}" data-preference-decided="{{ $branding['preference_decided'] ? '1' : '0' }}" data-theme-key="{{ $branding['theme_key'] }}" data-default-theme="{{ $branding['default_theme'] }}" style="--brand-light:{{ $branding['accent_light'] }};--brand-dark:{{ $branding['accent_dark'] }}@foreach($branding['theme_tokens'] as $token => $value);--{{ $token }}:{{ $value }}@endforeach">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace') · {{ $branding['app_name'] }}</title>
    <link rel="icon" href="{{ $branding['favicon'] }}">
    <script src="{{ asset('client-palette.js') }}?v=2"></script>
    <script src="{{ asset('appearance.js') }}?v=6"></script>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=scalyn-7">
    <script src="{{ asset('navigation.js') }}" defer></script>
    @livewireStyles
</head>
<body class="@auth app-layout @else guest-layout @endauth">
<a class="skip" href="#main">Skip to content</a>
@auth
<header class="mobile-bar"><button type="button" class="icon-button menu-toggle" id="nav-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><x-icon name="menu"/></button><span>{{ $branding['app_name'] }}</span></header>
<div class="nav-backdrop menu-backdrop" id="nav-backdrop" hidden></div>
<aside class="app-sidebar" id="app-sidebar" aria-label="Workspace menu">
    <div class="sidebar-top">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="{{ $branding['brand_name'] }} {{ $branding['app_name'] }} home">
            <span class="brand-symbol">
            @if($branding['logo_light'])<img class="brand-logo logo-light" src="{{ $branding['logo_light'] }}" alt="{{ $branding['brand_name'] }}"><img class="brand-logo logo-dark" src="{{ $branding['logo_dark'] }}" alt="{{ $branding['brand_name'] }}">
            @else<img src="{{ $branding['compact_logo'] ?: '/images/training-mark.svg' }}" alt="" width="36" height="36">@endif
            </span>
            <span class="brand-copy"><strong>{{ $branding['brand_name'] }}</strong><span>{{ $branding['app_name'] }}</span></span>
        </a>
        <button type="button" class="icon-button nav-close menu-close" id="nav-close" aria-label="Close navigation"><x-icon name="close"/></button>
    </div>
    @if($branding['mode'] === 'co-branded')<p class="co-brand">Part of Cerebru</p>@endif
    <p class="workspace-nav-label">Your workspace</p>
    <nav class="sidebar-nav" aria-label="Main navigation">
        @if(app(\App\Services\Tenancy\ClientAccess::class)->globalAdmin(auth()->user()))
        <a href="{{ route('platform.overview') }}" @if(request()->routeIs('platform.*')) aria-current="page" @endif><x-icon name="grid"/><span>Platform administration</span></a>
        @endif
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="home"/><span>Overview</span></a>
        <a href="{{ route('dashboard') }}#learning-plans" @if(request()->routeIs('enrolments.*', 'onboarding.*', 'plans.*', 'delivery.*', 'submission.*', 'progress.*', 'documents.*', 'files.*', 'jobs.*', 'ai.request', 'ai.show')) aria-current="page" @endif><x-icon name="book"/><span>Learning plans</span></a>
        @if(app(\App\Services\Access::class)->coordinator(auth()->user()))<a href="{{ route('reviews.show') }}" @if(request()->routeIs('reviews.*')) aria-current="page" @endif><x-icon name="check"/><span>Review queue</span></a>@endif
        <a href="{{ route('notifications.show') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif><x-icon name="bell"/><span>Updates</span></a>
        <span class="nav-section">Manage</span>
        @if(app(\App\Services\Access::class)->admin(auth()->user()))
        <a href="{{ route('people') }}" @if(request()->routeIs('people')) aria-current="page" @endif><x-icon name="users"/><span>People</span></a>
        <a href="{{ route('administration.show') }}" @if(request()->routeIs('administration.*')) aria-current="page" @endif><x-icon name="grid"/><span>Administration</span></a>
        <a href="{{ route('ai.settings') }}" @if(request()->routeIs('ai.settings', 'ai.policy', 'ai.reconcile')) aria-current="page" @endif><x-icon name="spark"/><span>AI settings</span></a>
        @endif
        <a href="{{ route('appearance') }}" @if(request()->routeIs('appearance')) aria-current="page" @endif><x-icon name="settings"/><span>Appearance</span></a>
    </nav>
    <div class="sidebar-bottom">
        @if($branding['cerebru_url'])<a class="parent-link" href="{{ $branding['cerebru_url'] }}"><x-icon name="external"/>Back to Cerebru</a>@endif
        <p class="sidebar-message"><span>One step at a time</span>Progress starts<br>with showing up.</p>
        <x-theme-picker :branding="$branding"/>
    </div>
</aside>
@else
<header class="guest-header"><a class="brand" href="{{ route('login') }}"><img src="{{ $branding['logo_light'] ?: $branding['compact_logo'] ?: '/images/training-mark.svg' }}" class="brand-logo logo-light" alt=""><img src="{{ $branding['logo_dark'] ?: $branding['compact_logo'] ?: '/images/training-mark.svg' }}" class="brand-logo logo-dark" alt=""><span class="brand-copy"><strong>{{ $branding['brand_name'] }}</strong><span>{{ $branding['app_name'] }}</span></span></a><x-theme-picker :branding="$branding"/></header>
@endauth
<div class="app-content" id="app-content">
@auth
<header class="workspace-header">
@php($clientContext = app(\App\Services\Tenancy\ClientContext::class))
@php($clientChoices = $clientContext->choices(auth()->user()))
<form class="client-switcher" method="post" action="{{ route('clients.select') }}" aria-label="Active client">@csrf
<label for="active-client">Active client</label><div class="client-controls"><select id="active-client" name="client_id" required>
<option value="">Choose client</option>@foreach($clientChoices as $client)<option value="{{ $client->id }}" @selected($clientContext->id === $client->id)>{{ $client->name }}</option>@endforeach
</select><button type="submit" class="secondary">Switch client</button></div></form><div class="profile"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>Current client access</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out"><x-icon name="logout"/></button></form></div></header>
@endauth
@if(config('training.mock_identity') && config('training.environment') === 'test')<div class="sandbox"><span class="dot"></span><strong>LOCAL SANDBOX</strong><span>Synthetic people · Mock sign-in · No email delivery</span></div>@endif
<main id="main" class="container" tabindex="-1">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert"><strong>Please check your input.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer><span>{{ $branding['app_name'] }}</span><span>{{ $branding['footer'] }}</span></footer>
</div>
<div class="sr-only" role="status" aria-live="polite" id="appearance-status"></div>
@livewireScripts
</body>
</html>
