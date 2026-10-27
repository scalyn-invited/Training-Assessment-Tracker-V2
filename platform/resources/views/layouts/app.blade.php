<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace') · Training Tracker</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <script src="{{ asset('navigation.js') }}" defer></script>
    @livewireStyles
</head>
<body class="@auth has-sidebar @endauth">
<a class="skip" href="#main">Skip to content</a>
@auth
@php
    $access = app(\App\Services\Access::class);
    $menu = [
        ['Workspace', 'dashboard', true, request()->routeIs('dashboard', 'enrolments.*', 'onboarding.*', 'plans.*', 'delivery.*', 'progress.*', 'documents.*', 'ai.show', 'submission.*')],
        ['People', 'people', $access->admin(auth()->user()), request()->routeIs('people')],
        ['Review queue', 'reviews.show', $access->coordinator(auth()->user()), request()->routeIs('reviews.*')],
        ['Updates', 'notifications.show', true, request()->routeIs('notifications.*')],
        ['Administration', 'administration.show', $access->admin(auth()->user()), request()->routeIs('administration.*')],
        ['AI settings', 'ai.settings', $access->admin(auth()->user()), request()->routeIs('ai.settings', 'ai.policy', 'ai.reconcile')],
    ];
@endphp
<aside class="sidebar" id="app-sidebar" aria-label="Workspace menu">
    <div class="sidebar-heading">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">T</span><span>Training Tracker<small>Learning workspace</small></span></a>
        <button type="button" class="menu-close secondary" aria-label="Close menu" hidden>Close</button>
    </div>
    <p class="nav-caption">Your workspace</p>
    <nav aria-label="Main navigation">
        @foreach($menu as [$label, $route, $visible, $active])
            @if($visible)<a href="{{ route($route) }}" @if($active) aria-current="page" @endif>{{ $label }}</a>@endif
        @endforeach
    </nav>
    <div class="sidebar-note">Human decisions.<br>Traceable progress.</div>
</aside>
<div class="menu-backdrop" hidden></div>
@endauth
<div class="app-content" id="app-content">
<header class="topbar">
    @guest
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">T</span> Training Tracker <span class="version">V2</span></a>
    @endguest
    @auth
        <button type="button" class="menu-toggle secondary" aria-controls="app-sidebar" aria-expanded="false" hidden>Menu</button>
        @php($clientContext = app(\App\Services\Tenancy\ClientContext::class))
        @php($clientChoices = $clientContext->choices(auth()->user()))
        <form class="client-switcher" method="post" action="{{ route('clients.select') }}" aria-label="Active client">@csrf
            <label for="active-client">Active client</label>
            <div class="client-controls"><select id="active-client" name="client_id" required>
                <option value="">Choose client</option>@foreach($clientChoices as $client)<option value="{{ $client->id }}" @selected($clientContext->id === $client->id)>{{ $client->name }}</option>@endforeach
            </select><button type="submit" class="secondary">Switch client</button></div>
        </form>
        <div class="account"><span>{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="secondary">Sign out</button></form></div>
    @endauth
</header>
<div class="sandbox"><span class="dot"></span> LOCAL SANDBOX <span>Synthetic people · Mock sign-in · No email delivery</span></div>
<main id="main" class="container" tabindex="-1">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert"><strong>Please check your input.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer>Training Assessment Tracker · Learning workspace <span>Human decisions. Traceable progress.</span></footer>
</div>
@livewireScripts
</body>
</html>
