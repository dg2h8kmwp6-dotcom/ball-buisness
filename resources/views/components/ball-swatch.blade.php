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
        {{ $attributes->merge(['class' => $class.' shrink-0 rounded-full border-2 border-pit-950 object-cover']) }}
    >
@else
    <span
        aria-hidden="true"
        style="background: radial-gradient(circle at 34% 28%, rgba(255,255,255,.8) 0%, {{ $ballType->color_hex }} 36%, {{ $ballType->color_hex }} 100%);"
        {{ $attributes->merge(['class' => $class.' shrink-0 rounded-full border-2 border-pit-950']) }}
    ></span>
@endif
