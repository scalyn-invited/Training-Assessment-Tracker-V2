@props(['name' => 'grid'])
<svg {{ $attributes->merge(['class' => 'icon']) }} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('home')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>@break
@case('book')<path d="M12 5v16M3 4h5a4 4 0 0 1 4 2 4 4 0 0 1 4-2h5v15h-5a4 4 0 0 0-4 2 4 4 0 0 0-4-2H3Z"/>@break
@case('check')<path d="m8 12 3 3 5-6"/><rect x="4" y="3" width="16" height="18" rx="3"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('settings')<path d="m12 3 2 3 4-1 1 4 3 3-3 2-1 4-4-1-2 4-2-4-4 1-1-4-3-2 3-3 1-4 4 1Z"/><circle cx="12" cy="12" r="3"/>@break
@case('spark')<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5ZM20 2v4M18 4h4"/>@break
@case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
@case('external')<path d="M14 3h7v7m0-7L10 14M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>@break
@case('logout')<path d="M10 4H4v16h6M9 12h12m-4-4 4 4-4 4"/>@break
@case('moon')<path d="M20.5 13a8.5 8.5 0 0 1-9.5-9.5A8.5 8.5 0 1 0 20.5 13Z"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
@case('chart')<path d="M4 3v18h17M8 16v-5M13 16V7M18 16v-8"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
@endswitch
</svg>
