<x-layout title="Your bag">
    <div class="mx-auto w-full max-w-xl px-4 py-16 sm:px-6">
        <div class="rounded-2xl border border-pit-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-pit-100">
                <svg class="size-7 text-pit-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M6 7h12l-1.2 12.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 7Z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M9.5 7V5.5a2.5 2.5 0 0 1 5 0V7" stroke-linecap="round" />
                </svg>
            </div>

            <h1 class="mt-5 text-2xl font-bold tracking-tight">Your bag is empty</h1>
            <p class="mx-auto mt-3 max-w-sm text-pit-700">
                Build a mix first — choose your colours and quantities, and watch them drop into the
                bag. You will come back here to check out.
            </p>

            <a
                href="{{ route('order.create') }}"
                class="mt-8 inline-flex items-center gap-2 rounded-xl bg-pit-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2"
            >
                Build your mix
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
</x-layout>
