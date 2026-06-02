@php
    $icon = $icon ?? 'wave';
    $class = $class ?? 'h-3.5 w-3.5';
@endphp

@switch($icon)
    @case('alert')
        <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v4m0 4h.01M10.3 4.3 2.8 18a1.5 1.5 0 0 0 1.3 2.2h15.8a1.5 1.5 0 0 0 1.3-2.2L13.7 4.3a1.5 1.5 0 0 0-2.4 0Z" />
        </svg>
        @break

    @case('check')
        <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="m5 13 4 4L19 7" />
        </svg>
        @break

    @case('question')
        <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9.1 9a3 3 0 1 1 4.9 2.3c-.9.6-1.5 1.2-1.5 2.4v.3M12 18h.01" />
        </svg>
        @break

    @case('minus')
        <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M6 12h12" />
        </svg>
        @break

    @default
        <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 14c2.5 0 2.5-4 5-4s2.5 4 5 4 2.5-4 5-4" />
        </svg>
@endswitch
