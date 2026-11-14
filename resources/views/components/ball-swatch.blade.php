@props(['ballType', 'class' => 'size-10'])

{{--
    A single ball, shown as the uploaded photo when there is one and as a
    generated gloss gradient otherwise.
--}}
@if ($ballType->hasImage())
    <img
        src="{{ $ballType->imageUrl() }}"
        alt="{{ $ballType->name }}"
        loading="lazy"
        {{ $attributes->merge(['class' => $class.' shrink-0 rounded-full object-cover ring-1 ring-inset ring-black/10']) }}
    >
@else
    <span
        aria-hidden="true"
        style="background: radial-gradient(circle at 34% 30%, rgba(255,255,255,.72) 0%, {{ $ballType->color_hex }} 38%, rgba(0,0,0,.14) 100%);"
        {{ $attributes->merge(['class' => $class.' shrink-0 rounded-full ring-1 ring-inset ring-black/10']) }}
    ></span>
@endif
