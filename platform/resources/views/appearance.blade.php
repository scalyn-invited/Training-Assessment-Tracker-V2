@extends('layouts.app')
@section('title', 'Appearance')
@section('content')
<div class="page-heading"><div><p class="eyebrow">YOUR WORKSPACE</p><h1>Appearance</h1><p class="lead">Make your learning space feel right for you.</p></div></div>
<section class="panel" aria-labelledby="themes-heading"><h2 id="themes-heading">Choose your theme</h2><p>Choose a look for your account in this client, across devices. Use the client default to follow your administrator’s choice.</p>
<div class="theme-gallery" role="group" aria-label="Workspace theme">
@foreach($branding['themes'] as $value => $label)
<button type="button" class="theme-tile" data-theme-choice="{{ $value }}" aria-pressed="false"><span class="theme-preview sample-{{ $value }}" aria-hidden="true"></span><span>{{ $label }}</span><span class="theme-check" aria-hidden="true">✓</span></button>
@endforeach
</div><div class="form-actions"><button type="button" class="secondary" data-theme-reset>Use client default</button></div>
</section>
@if(app(\App\Services\Access::class)->admin(auth()->user()))
<section class="panel appearance-section" aria-labelledby="branding-heading"><h2 id="branding-heading">Client branding</h2><p>Save branding for the selected client. Everyone in this client sees your saved changes on their next page load.</p>
<div class="automatic-branding-editor"><form id="brand-preview-form" method="post" action="{{ route('branding.save') }}" enctype="multipart/form-data" data-save-branding>
@csrf
<input type="hidden" name="client_id" value="{{ app(\App\Services\Tenancy\ClientContext::class)->id }}">
<input type="hidden" name="version" value="{{ old('version', $branding['version']) }}">
@if($errors->any())<div class="notice error" role="alert"><p>Branding was not saved.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<label for="client-default-theme">Client default theme</label>
<select id="client-default-theme" name="default_theme"><option value="">Use configured default</option>
@foreach(\App\Services\Branding::THEMES + ['client-light'=>'Client Light','client-dark'=>'Client Dark'] as $value => $label)
<option value="{{ $value }}" @selected(old('default_theme', $branding['saved_default_theme'] === 'client' ? 'client-light' : $branding['saved_default_theme']) === $value)>{{ $label }}</option>
@endforeach
</select><p>Applies to people using the client default. Personal choices stay unchanged.</p>
<label for="preview-mode">Branding mode</label><select id="preview-mode" name="preview_mode"><option value="cerebru" @selected(old('preview_mode', $branding['mode']) === 'cerebru')>Cerebru</option><option value="co-branded" @selected(old('preview_mode', $branding['mode']) === 'co-branded')>Client + Cerebru</option><option value="white-label" @selected(old('preview_mode', $branding['mode']) === 'white-label')>Client only (white-label)</option></select>
<label for="preview-client">Client name</label><input id="preview-client" name="preview_client" maxlength="120" value="{{ old('preview_client', $branding['client_name'] ?: 'Your organisation') }}">
<label for="preview-name">Application name</label><input id="preview-name" name="preview_name" maxlength="120" value="{{ old('preview_name', $branding['app_name']) }}">
<label for="preview-accent">Brand colour</label>
<div class="brand-colour-control">
<input type="color" id="preview-accent" value="{{ old('preview_accent', $branding['brand_source']) }}" aria-describedby="brand-colour-help">
<label for="brand-colour-hex" class="sr-only">Brand colour hex value</label><input id="brand-colour-hex" name="preview_accent" type="text" value="{{ old('preview_accent', $branding['brand_source']) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" required spellcheck="false" aria-describedby="brand-colour-help" placeholder="#1d4668">
</div>
<p id="brand-colour-help">Choose one colour. We adjust its shades to create readable light and dark themes. Save, then select Client Light or Client Dark from your theme choices.</p>
<p id="brand-colour-status" role="status" aria-live="polite"></p>
<div class="auto-theme-previews" aria-label="Generated theme previews">
@foreach($branding['theme_previews'] as $variant => $tokens)
<section class="auto-theme-preview" data-palette-preview="{{ $variant }}" aria-label="Client {{ ucfirst($variant) }} preview" style="@foreach($tokens as $key => $value)--preview-{{ $key }}:{{ $value }};@endforeach">
<h3>Client {{ ucfirst($variant) }}</h3>
<img class="preview-logo" src="{{ $branding['logo_light'] ?: '/images/training-mark.svg' }}" alt="">
<strong data-preview-brand>{{ $branding['brand_name'] }}</strong><p data-preview-name>{{ $branding['app_name'] }}</p><span data-preview-parent>Part of Cerebru</span>
<div class="auto-preview-card"><span class="auto-preview-active">Active navigation</span><h4>Your learning workspace</h4><p>Readable text and comfortable cards.</p><span class="auto-preview-button">Button preview</span></div>
</section>
@endforeach
</div>
<label for="brand-logo-upload">Client logo</label>
<input id="brand-logo-upload" type="file" name="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help">
<p id="logo-help">PNG, JPEG or WebP. Maximum 2 MB and 4096 × 4096 pixels. One logo is used for all themes.</p>
@if($branding['custom_logo'])<label><input type="checkbox" name="remove_logo" value="1"> Remove custom logo and use the configured default</label>@endif
<p>The client name is a display label; the organisation directory name stays unchanged.</p>
<div class="form-actions"><button class="primary" type="submit">Save client branding</button><a class="secondary" href="{{ route('appearance') }}">Discard changes</a></div>
</form></div>
</section>
@endif
@endsection
