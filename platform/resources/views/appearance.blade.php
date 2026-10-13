@extends('layouts.app')
@section('title', 'Appearance')
@section('content')
<div class="page-heading"><div><p class="eyebrow">YOUR WORKSPACE</p><h1>Appearance</h1><p class="lead">Make your learning space feel right for you.</p></div></div>
<section class="panel" aria-labelledby="themes-heading"><h2 id="themes-heading">Choose your theme</h2><p>Choose a look for this account on this browser. Your preference stays with you as you move between pages.</p>
<div class="theme-gallery" role="group" aria-label="Workspace theme">
@foreach($branding['themes'] as $value => $label)
<button type="button" class="theme-tile" data-theme-choice="{{ $value }}" aria-pressed="false"><span class="theme-preview sample-{{ $value }}" aria-hidden="true"></span><span>{{ $label }}</span><span class="theme-check" aria-hidden="true">✓</span></button>
@endforeach
</div><div class="form-actions"><button type="button" class="secondary" data-theme-reset>Use workspace default</button></div>
</section>
@if(auth()->user()->role === 'admin')
<section class="panel appearance-section" aria-labelledby="branding-heading"><h2 id="branding-heading">Branding preview</h2><p>Explore your client identity before it is configured. This preview changes only the sample below; it does not save or change anyone’s workspace.</p>
<div class="detail-grid"><form id="brand-preview-form">
<label for="preview-mode">Branding mode</label><select id="preview-mode" name="preview_mode"><option value="cerebru" @selected($branding['mode'] === 'cerebru')>Cerebru</option><option value="co-branded" @selected($branding['mode'] === 'co-branded')>Client + Cerebru</option><option value="white-label" @selected($branding['mode'] === 'white-label')>Client only (white-label)</option></select>
<label for="preview-client">Client name</label><input id="preview-client" name="preview_client" maxlength="120" value="{{ $branding['client_name'] ?: 'Your organisation' }}">
<label for="preview-name">Application name</label><input id="preview-name" name="preview_name" maxlength="120" value="{{ $branding['app_name'] }}">
<label for="preview-accent">Brand accent</label><input type="color" id="preview-accent" name="preview_accent" value="{{ $branding['accent_dark'] }}">
<button class="secondary" type="reset">Reset preview</button>
</form><div><div class="branding-preview" id="brand-preview"><img class="preview-logo" src="{{ $branding['compact_logo'] ?: '/images/training-mark.svg' }}" alt=""><span class="preview-caption">BRAND PREVIEW</span><strong data-preview-brand>{{ $branding['brand_name'] }}</strong><p data-preview-name>{{ $branding['app_name'] }}</p><span data-preview-parent class="preview-caption">Part of Cerebru</span><hr style="border:0;border-top:1px solid var(--line);width:100%"><h3>Your learning workspace</h3><p>Focused training. Thoughtful feedback. A clear next step.</p></div><p class="preview-caption">The selected theme also applies to this preview.</p></div></div>
</section>
@endif
@endsection
