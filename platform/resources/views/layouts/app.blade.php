<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace') · Training Tracker</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    @livewireStyles
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">T</span> Training Tracker <span class="version">V2</span></a>
    @auth
    <nav aria-label="Main navigation">
        <a href="{{ route('notifications.show') }}">Updates</a>
        @if(auth()->user()->role === 'coordinator' && auth()->user()->sensitive_access)<a href="{{ route('reviews.show') }}">Review queue</a>@endif
        @if(auth()->user()->role === 'admin')<a href="{{ route('ai.settings') }}">AI settings</a>@endif
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Workspace</a>
        @if(auth()->user()->role === 'admin')<a href="{{ route('people') }}">People</a>@endif
        @if(auth()->user()->role === 'admin')<a href="{{ route('administration.show') }}">Administration</a>@endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="quiet">Sign out</button></form>
    </nav>
    @endauth
</header>
<div class="sandbox"><span class="dot"></span> LOCAL SANDBOX <span>Synthetic people · Mock sign-in · No email delivery</span></div>
<main id="main" class="container">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert"><strong>Please check your input.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer>Training Assessment Tracker · Learning workspace <span>Human decisions. Traceable progress.</span></footer>
@livewireScripts
</body>
</html>
