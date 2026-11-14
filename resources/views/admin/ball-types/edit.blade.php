@php
    $isNew = ! $ballType->exists;
@endphp

<x-admin-layout :title="$isNew ? 'Add a ball type' : 'Edit '.$ballType->name">
    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
        <a href="{{ route('admin.ball-types.index') }}"
            class="font-display text-sm font-bold tracking-tight text-pit-700 underline decoration-2 underline-offset-4 hover:text-berry">
            &larr; All ball types
        </a>

        <h1 class="mt-3 font-display text-3xl font-extrabold tracking-tighter">
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
            <section class="card p-6">
                <h2 class="font-display text-xl font-extrabold tracking-tight">Ball photo</h2>
                <p class="mt-1 text-sm text-pit-600">
                    Upload a photo of the real ball, then drag and zoom to frame it in the circle.
                    Leave it empty to use the flat colour below instead.
                </p>

                <div class="mt-5 flex flex-wrap items-start gap-6">
                    <div class="shrink-0">
                        <p class="eyebrow">Current</p>
                        <div class="mt-2">
                            <x-ball-swatch :ball-type="$ballType" class="size-24" />
                        </div>

                        @if ($ballType->hasImage())
                            <form method="POST" action="{{ route('admin.ball-types.photo.destroy', $ballType) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-display text-xs font-bold text-berry underline decoration-2 underline-offset-4">
                                    Remove photo
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="min-w-64 flex-1">
                        <label for="photo" class="label-strong">Choose a photo</label>
                        <input
                            id="photo"
                            name="photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            data-photo-input
                            class="mt-1.5 block w-full cursor-pointer rounded-xl border-2 border-pit-300 bg-white text-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-sun file:px-4 file:py-3 file:font-display file:text-sm file:font-bold file:text-pit-950 focus:border-pit-950 focus:outline-none focus:ring-4 focus:ring-sun"
                        >
                        <p class="hint">JPEG, PNG, WebP or GIF, up to 12MB.</p>

                        @error('photo')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('crop_size')
                            <p class="error-text">{{ $message }}</p>
                        @enderror

                        {{-- Cropper, revealed once a file is chosen --}}
                        <div data-cropper hidden class="mt-5">
                            <div
                                data-cropper-viewport
                                class="relative mx-auto aspect-square w-full max-w-72 cursor-grab touch-none overflow-hidden rounded-2xl border-2 border-pit-950 bg-pit-100 select-none active:cursor-grabbing"
                            >
                                <img data-cropper-image alt="" class="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left">

                                {{-- Circular guide showing how the ball will be framed --}}
                                <div class="pointer-events-none absolute inset-0 shadow-[inset_0_0_0_9999px_rgba(255,255,255,0.55)] [clip-path:circle(50%_at_50%_50%)]"></div>
                                <div class="pointer-events-none absolute inset-0 rounded-full ring-4 ring-pit-950/80"></div>
                            </div>

                            <div class="mx-auto mt-3 flex max-w-72 items-center gap-3">
                                <span class="eyebrow">Zoom</span>
                                <input
                                    type="range"
                                    data-cropper-zoom
                                    min="1" max="4" step="0.01" value="1"
                                    aria-label="Zoom"
                                    class="h-2 w-full cursor-pointer appearance-none rounded-full border-2 border-pit-950 bg-white accent-berry"
                                >
                                <button type="button" data-cropper-reset class="shrink-0 font-display text-xs font-bold text-pit-700 underline decoration-2 underline-offset-4">
                                    Reset
                                </button>
                            </div>

                            <p class="mt-2 text-center text-xs font-medium text-pit-600">
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
            <section class="card p-6">
                <h2 class="font-display text-xl font-extrabold tracking-tight">Details</h2>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-admin-field name="name" label="Name" :value="old('name', $ballType->name)" required />
                    </div>

                    <div>
                        <label for="color_hex" class="label-strong">
                            Flat colour
                        </label>
                        <p class="hint">Used when there is no photo, and for the bag animation.</p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input
                                type="color"
                                value="{{ old('color_hex', $ballType->color_hex ?? '#2b7fd4') }}"
                                aria-label="Colour picker"
                                class="size-11 shrink-0 cursor-pointer rounded-xl border-2 border-pit-950 bg-white p-1"
                                oninput="document.getElementById('color_hex').value = this.value"
                            >
                            <input
                                id="color_hex"
                                name="color_hex"
                                type="text"
                                required
                                value="{{ old('color_hex', $ballType->color_hex ?? '#2b7fd4') }}"
                                class="field font-mono text-sm"
                            >
                        </div>
                        @error('color_hex')
                            <p class="error-text">{{ $message }}</p>
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
                        <label for="description" class="label-strong">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="field mt-1.5"
                        >{{ old('description', $ballType->description) }}</textarea>
                        @error('description')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2.5 font-display text-sm font-bold tracking-tight">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $ballType->is_active ?? true))
                                class="size-4 rounded border-2 border-pit-950 text-berry focus:ring-2 focus:ring-sun"
                            >
                            Show this ball type in the shop
                        </label>
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="btn-primary">
                    {{ $isNew ? 'Add ball type' : 'Save changes' }}
                </button>
                <a href="{{ route('admin.ball-types.index') }}" class="font-display text-sm font-bold text-pit-700 underline decoration-2 underline-offset-4">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-admin-layout>
