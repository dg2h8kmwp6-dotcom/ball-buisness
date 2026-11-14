@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-pit-800">{{ $label }}</label>

    @if ($hint)
        <p class="mt-0.5 text-xs text-pit-500">{{ $hint }}</p>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @required($required)
        {{ $attributes->merge([
            'class' => 'mt-1.5 block w-full rounded-lg border px-3 py-2.5 shadow-sm focus:outline-none focus:ring-2 '
                .($errors->has($name)
                    ? 'border-red-400 focus:border-red-500 focus:ring-red-500/30'
                    : 'border-pit-300 focus:border-pit-500 focus:ring-pit-500/30'),
        ]) }}
    >

    @error($name)
        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
