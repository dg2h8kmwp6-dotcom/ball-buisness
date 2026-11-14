@props(['class' => 'size-10'])

@php
    // Unique per instance so two logos on one page cannot clash on the mask id.
    $maskId = 'logo-thumb-hole-'.\Illuminate\Support\Str::random(6);
@endphp

{{--
    Placeholder mark: a painter's palette whose paint blobs are pit balls,
    using the catalogue colours. Replace with the real logo.
--}}
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <mask id="{{ $maskId }}">
        <rect width="48" height="48" fill="#fff" />
        <ellipse cx="30.5" cy="31.5" rx="4.6" ry="3.7" fill="#000" />
    </mask>

    <g mask="url(#{{ $maskId }})">
        <path
            d="M23.5 4.5c10.6 0 19.5 7 19.5 15.8 0 7.3-5.4 11.3-11.4 12.2-3.3.5-5.4 1.9-5.4 4.7 0 2.9-1.9 5.3-4.8 5.3C11.2 42.5 4 34.4 4 23.9 4 13.1 12.9 4.5 23.5 4.5Z"
            fill="#f4f1ea"
            stroke="#2b2f45"
            stroke-opacity="0.22"
            stroke-width="1.6"
        />

        {{-- Paint blobs, in the catalogue colours. --}}
        <circle cx="14.5" cy="17.5" r="3.5" fill="#e8392e" />
        <circle cx="23" cy="12" r="3.5" fill="#f6c62b" />
        <circle cx="32" cy="15.5" r="3.5" fill="#2b7fd4" />
        <circle cx="36.5" cy="24" r="3.5" fill="#3faa58" />
        <circle cx="13" cy="27.5" r="3.5" fill="#8257c9" />

        {{-- A touch of gloss so the blobs read as plastic balls. --}}
        <circle cx="13.4" cy="16.4" r="1.1" fill="#fff" fill-opacity="0.6" />
        <circle cx="21.9" cy="10.9" r="1.1" fill="#fff" fill-opacity="0.6" />
        <circle cx="30.9" cy="14.4" r="1.1" fill="#fff" fill-opacity="0.6" />
        <circle cx="35.4" cy="22.9" r="1.1" fill="#fff" fill-opacity="0.6" />
        <circle cx="11.9" cy="26.4" r="1.1" fill="#fff" fill-opacity="0.6" />
    </g>
</svg>
