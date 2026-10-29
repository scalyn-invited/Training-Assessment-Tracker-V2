@props(['branding'])
<details class="theme-picker">
    <summary><x-icon name="moon"/><span>Theme</span><span class="theme-current" data-theme-label>{{ $branding['themes'][$branding['selected_theme']] }}</span></summary>
    <div class="theme-options" role="group" aria-label="Colour theme">
        @foreach($branding['themes'] as $value => $label)
        <button type="button" data-theme-choice="{{ $value }}" aria-pressed="false"><span class="theme-dot theme-dot-{{ $value }}"></span>{{ $label }}<span class="theme-check" aria-hidden="true">✓</span></button>
        @endforeach
        @if($branding['appearance_scope'] === 'platform')
        <button type="button" data-theme-reset>Use platform default</button>
        @endif
    </div>
</details>
