@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
])

<div>
    <label for="{{ $name }}" class="label-strong">{{ $label }}</label>

    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @required($required)
        {{ $attributes->merge(['class' => 'field mt-1.5'.($errors->has($name) ? ' field-error' : '')]) }}
    >

    @error($name)
        <p class="error-text">{{ $message }}</p>
    @enderror
</div>
