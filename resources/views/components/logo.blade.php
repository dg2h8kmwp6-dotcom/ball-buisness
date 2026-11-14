@props(['class' => 'size-10'])

{{-- Placeholder mark: three stacked pit balls. Replace with the real logo. --}}
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <circle cx="16" cy="30" r="12" fill="#e8392e" />
    <circle cx="32" cy="30" r="12" fill="#f6c62b" />
    <circle cx="24" cy="16" r="12" fill="#2b7fd4" />
    <circle cx="20" cy="12" r="3.5" fill="#ffffff" fill-opacity="0.55" />
</svg>
