<x-admin-layout title="Ball types">
    <div class="mx-auto w-full max-w-5xl px-4 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-extrabold tracking-tighter">Ball types</h1>
                <p class="mt-1 text-sm text-pit-600">
                    {{ $ballTypes->count() }} in the catalogue · {{ $ballTypes->where('is_active', true)->count() }} on sale
                </p>
            </div>

            <a href="{{ route('admin.ball-types.create') }}"
                class="btn-primary">
                Add a ball type
            </a>
        </div>

        <div class="card mt-6 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b-2 border-pit-950 bg-sun text-xs uppercase tracking-wider text-pit-950">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-display font-extrabold">Ball</th>
                        <th scope="col" class="px-4 py-3 font-display font-extrabold">Photo</th>
                        <th scope="col" class="px-4 py-3 text-right font-display font-extrabold">Price</th>
                        <th scope="col" class="px-4 py-3 text-right font-display font-extrabold">Ordered</th>
                        <th scope="col" class="px-4 py-3 font-display font-extrabold">Status</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-dashed divide-pit-200">
                    @forelse ($ballTypes as $ballType)
                        <tr class="align-middle">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-ball-swatch :ball-type="$ballType" class="size-9" />
                                    <div class="min-w-0">
                                        <p class="font-display font-bold tracking-tight">{{ $ballType->name }}</p>
                                        <p class="font-mono text-xs text-pit-500">{{ $ballType->color_hex }} · {{ $ballType->diameter_mm }}mm</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                @if ($ballType->hasImage())
                                    <span class="tape border-grass bg-grass/15 text-pit-900">
                                        Uploaded
                                    </span>
                                @else
                                    <span class="tape border-pit-300 bg-pit-100 text-pit-600">
                                        Flat colour
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums">{{ $ballType->formattedUnitPrice() }}</td>

                            <td class="px-4 py-3 text-right tabular-nums text-pit-600">
                                {{ number_format($ballType->order_items_count) }}
                            </td>

                            <td class="px-4 py-3">
                                @if ($ballType->is_active)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-pit-900">
                                        <span class="size-2 rounded-full border border-pit-950 bg-grass"></span> On sale
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-pit-500">
                                        <span class="size-2 rounded-full border border-pit-400 bg-pit-200"></span> Hidden
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.ball-types.edit', $ballType) }}"
                                        class="font-display text-sm font-bold text-pit-800 underline decoration-2 underline-offset-4 hover:text-sky">
                                        Edit
                                    </a>

                                    @if ($ballType->order_items_count === 0)
                                        <form method="POST" action="{{ route('admin.ball-types.destroy', $ballType) }}"
                                            onsubmit="return confirm('Delete {{ addslashes($ballType->name) }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-display text-sm font-bold text-berry underline decoration-2 underline-offset-4">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-pit-600">
                                <p class="font-display text-lg font-extrabold tracking-tight">No ball types yet.</p>
                                <p class="mt-1 text-sm">
                                    Run <code class="rounded bg-pit-200 px-1 py-0.5 font-mono text-xs">php artisan ballpit:sync-ball-types</code>
                                    to load the 12 defaults, or add one by hand.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-pit-500">
            Sort order controls the order colours appear in the shop. Ball types that appear in past
            orders can be hidden but not deleted, so order history stays intact.
        </p>
    </div>
</x-admin-layout>
