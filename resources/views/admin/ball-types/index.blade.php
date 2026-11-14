<x-admin-layout title="Ball types">
    <div class="mx-auto w-full max-w-5xl px-4 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Ball types</h1>
                <p class="mt-1 text-sm text-pit-600">
                    {{ $ballTypes->count() }} in the catalogue · {{ $ballTypes->where('is_active', true)->count() }} on sale
                </p>
            </div>

            <a href="{{ route('admin.ball-types.create') }}"
                class="rounded-xl bg-pit-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2">
                Add a ball type
            </a>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-pit-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-pit-200 bg-pit-50 text-xs uppercase tracking-wide text-pit-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Ball</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Photo</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Price</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Ordered</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pit-100">
                    @forelse ($ballTypes as $ballType)
                        <tr class="align-middle">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-ball-swatch :ball-type="$ballType" class="size-9" />
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $ballType->name }}</p>
                                        <p class="font-mono text-xs text-pit-500">{{ $ballType->color_hex }} · {{ $ballType->diameter_mm }}mm</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                @if ($ballType->hasImage())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                        Uploaded
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-pit-100 px-2 py-0.5 text-xs font-medium text-pit-600">
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
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-800">
                                        <span class="size-1.5 rounded-full bg-green-600"></span> On sale
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-pit-500">
                                        <span class="size-1.5 rounded-full bg-pit-400"></span> Hidden
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.ball-types.edit', $ballType) }}"
                                        class="font-medium text-pit-700 underline-offset-4 hover:underline">
                                        Edit
                                    </a>

                                    @if ($ballType->order_items_count === 0)
                                        <form method="POST" action="{{ route('admin.ball-types.destroy', $ballType) }}"
                                            onsubmit="return confirm('Delete {{ addslashes($ballType->name) }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-red-700 underline-offset-4 hover:underline">
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
                                <p class="font-medium">No ball types yet.</p>
                                <p class="mt-1 text-sm">
                                    Run <code class="rounded bg-pit-100 px-1 py-0.5 font-mono text-xs">php artisan ballpit:sync-ball-types</code>
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
