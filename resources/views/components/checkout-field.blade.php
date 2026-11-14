@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'autocomplete' => null,
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
        value="{{ old($name) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        @class(['field mt-1.5', 'field-error' => $errors->has($name)])
    >

    @error($name)
        <p class="error-text">{{ $message }}</p>
    @enderror
</div>
