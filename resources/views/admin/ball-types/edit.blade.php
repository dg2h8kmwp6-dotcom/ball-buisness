@php
    $isNew = ! $ballType->exists;
@endphp

<x-admin-layout :title="$isNew ? 'Add a ball type' : 'Edit '.$ballType->name">
    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
        <a href="{{ route('admin.ball-types.index') }}"
            class="text-sm font-medium text-pit-600 underline-offset-4 hover:text-pit-900 hover:underline">
            &larr; All ball types
        </a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight">
            {{ $isNew ? 'Add a ball type' : 'Edit '.$ballType->name }}
        </h1>

        <form
            method="POST"
            action="{{ $isNew ? route('admin.ball-types.store') : route('admin.ball-types.update', $ballType) }}"
            enctype="multipart/form-data"
            class="mt-6 space-y-6"
            data-ball-photo-form
        >
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            {{-- Photo --}}
            <section class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Ball photo</h2>
                <p class="mt-1 text-sm text-pit-600">
                    Upload a photo of the real ball, then drag and zoom to frame it in the circle.
                    Leave it empty to use the flat colour below instead.
                </p>

                <div class="mt-5 flex flex-wrap items-start gap-6">
                    <div class="shrink-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-pit-500">Current</p>
                        <div class="mt-2">
                            <x-ball-swatch :ball-type="$ballType" class="size-24" />
                        </div>

                        @if ($ballType->hasImage())
                            <form method="POST" action="{{ route('admin.ball-types.photo.destroy', $ballType) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-700 underline-offset-4 hover:underline">
                                    Remove photo
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="min-w-64 flex-1">
                        <label for="photo" class="block text-sm font-medium text-pit-800">Choose a photo</label>
                        <input
                            id="photo"
                            name="photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            data-photo-input
                            class="mt-1.5 block w-full cursor-pointer rounded-lg border border-pit-300 bg-white text-sm shadow-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-pit-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-pit-800 hover:file:bg-pit-200 focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30"
                        >
                        <p class="mt-1.5 text-xs text-pit-500">JPEG, PNG, WebP or GIF, up to 12MB.</p>

                        @error('photo')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        @error('crop_size')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror

                        {{-- Cropper, revealed once a file is chosen --}}
                        <div data-cropper hidden class="mt-5">
                            <div
                                data-cropper-viewport
                                class="relative mx-auto aspect-square w-full max-w-72 cursor-grab touch-none overflow-hidden rounded-xl bg-pit-100 select-none active:cursor-grabbing"
                            >
                                <img data-cropper-image alt="" class="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left">

                                {{-- Circular guide showing how the ball will be framed --}}
                                <div class="pointer-events-none absolute inset-0 shadow-[inset_0_0_0_9999px_rgba(255,255,255,0.55)] [clip-path:circle(50%_at_50%_50%)]"></div>
                                <div class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-pit-600/70"></div>
                            </div>

                            <div class="mx-auto mt-3 flex max-w-72 items-center gap-3">
                                <span class="text-xs font-medium text-pit-500">Zoom</span>
                                <input
                                    type="range"
                                    data-cropper-zoom
                                    min="1" max="4" step="0.01" value="1"
                                    aria-label="Zoom"
                                    class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-pit-200 accent-pit-600"
                                >
                                <button type="button" data-cropper-reset class="shrink-0 text-xs font-medium text-pit-600 underline-offset-4 hover:underline">
                                    Reset
                                </button>
                            </div>

                            <p class="mt-2 text-center text-xs text-pit-500">
                                Drag the photo to reposition it.
                            </p>

                            <input type="hidden" name="crop_x" data-crop-x value="0">
                            <input type="hidden" name="crop_y" data-crop-y value="0">
                            <input type="hidden" name="crop_size" data-crop-size value="0">
                        </div>
                    </div>
                </div>
            </section>

            {{-- Details --}}
            <section class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Details</h2>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-admin-field name="name" label="Name" :value="old('name', $ballType->name)" required />
                    </div>

                    <div>
                        <label for="color_hex" class="block text-sm font-medium text-pit-800">
                            Flat colour
                        </label>
                        <p class="mt-0.5 text-xs text-pit-500">Used when there is no photo, and for the bag animation.</p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input
                                type="color"
                                value="{{ old('color_hex', $ballType->color_hex ?? '#2b7fd4') }}"
                                aria-label="Colour picker"
                                class="size-10 shrink-0 cursor-pointer rounded-lg border border-pit-300 bg-white p-1"
                                oninput="document.getElementById('color_hex').value = this.value"
                            >
                            <input
                                id="color_hex"
                                name="color_hex"
                                type="text"
                                required
                                value="{{ old('color_hex', $ballType->color_hex ?? '#2b7fd4') }}"
                                class="block w-full rounded-lg border border-pit-300 px-3 py-2.5 font-mono text-sm shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30"
                            >
                        </div>
                        @error('color_hex')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-admin-field
                        name="price_per_ball_cents"
                        label="Price per ball (cents)"
                        type="number"
                        :value="old('price_per_ball_cents', $ballType->price_per_ball_cents)"
                        min="1"
                        required
                    />

                    <x-admin-field
                        name="diameter_mm"
                        label="Diameter (mm)"
                        type="number"
                        :value="old('diameter_mm', $ballType->diameter_mm)"
                        min="10"
                        required
                    />

                    <x-admin-field
                        name="sort_order"
                        label="Sort order"
                        type="number"
                        :value="old('sort_order', $ballType->sort_order ?? 0)"
                        min="0"
                        hint="Lower numbers appear first in the shop."
                    />

                    <x-admin-field
                        name="min_quantity"
                        label="Minimum quantity"
                        type="number"
                        :value="old('min_quantity', $ballType->min_quantity)"
                        min="1"
                        required
                    />

                    <x-admin-field
                        name="quantity_step"
                        label="Quantity step"
                        type="number"
                        :value="old('quantity_step', $ballType->quantity_step)"
                        min="1"
                        required
                        hint="How much the +/- buttons change the quantity by."
                    />

                    <div class="sm:col-span-2">
                        <label for="description" class="block text-sm font-medium text-pit-800">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="mt-1.5 block w-full rounded-lg border border-pit-300 px-3 py-2.5 shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30"
                        >{{ old('description', $ballType->description) }}</textarea>
                        @error('description')
                            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2.5 text-sm font-medium text-pit-800">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $ballType->is_active ?? true))
                                class="rounded border-pit-300 text-pit-600 focus:ring-pit-500/30"
                            >
                            Show this ball type in the shop
                        </label>
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="rounded-xl bg-pit-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2">
                    {{ $isNew ? 'Add ball type' : 'Save changes' }}
                </button>
                <a href="{{ route('admin.ball-types.index') }}" class="text-sm font-medium text-pit-600 underline-offset-4 hover:underline">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-admin-layout>
