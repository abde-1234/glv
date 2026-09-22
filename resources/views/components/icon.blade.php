@props(['name'])

<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            @break
        @case('home')
            <path d="m3 11 9-8 9 8M5.5 9.5V21h13V9.5M9 21v-7h6v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('building')
            <path d="M4 21V6.5A1.5 1.5 0 0 1 5.5 5H15v16M8 9h3m-3 4h3m-3 4h3m4-7h3.5a1.5 1.5 0 0 1 1.5 1.5V21M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('card')
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M7 14h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('logout')
            <path d="M10 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h5M15 8l4 4-4 4m4-4H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m16.5 16.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            @break
        @case('arrow-right')
            <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('arrow-left')
            <path d="M19 12H5m5-5-5 5 5 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('car')
            <path d="m5 10 1.7-4h10.6l1.7 4m-14 0h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7" cy="14" r="1.2" fill="currentColor"/><circle cx="17" cy="14" r="1.2" fill="currentColor"/><path d="M5 17v2m14-2v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4m8-4v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('users')
            <path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5-1a3 3 0 0 0 0-5.8M21 20v-1.5a4 4 0 0 0-3-3.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('chart')
            <path d="M5 20V10m7 10V4m7 16v-7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
            @break
        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>
            @break
        @case('edit')
            <path d="m14 5 5 5M4 20l3.5-.8L19 7.7a2.1 2.1 0 0 0-3-3L4.8 16.2 4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('pause')
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M9.5 8.5v7m5-7v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('trash')
            <path d="M4 7h16m-10-3h4l1 3H9l1-3Zm-4 3 1 14h10l1-14M10 11v6m4-6v6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('dots')
            <circle cx="5" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="19" cy="12" r="1.5" fill="currentColor"/>
            @break
        @case('upload')
            <path d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('download')
            <path d="M12 4v12m0 0 5-5m-5 5-5-5M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('document')
            <path d="M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6m-6 4h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9" fill="currentColor"/><path d="M12 10v6m0-9h.01" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('phone')
            <path d="M8.1 3.5 10 8l-2.2 1.4a14 14 0 0 0 6.8 6.8L16 14l4.5 1.9-.5 4c-8.7.7-16.6-7.2-15.9-15.9l4-.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @default
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
    @endswitch
</svg>
