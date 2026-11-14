<x-layout title="Your bag">
    <div class="mx-auto w-full max-w-xl px-4 py-20 sm:px-6">
        <div class="card p-10 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full border-2 border-pit-950 bg-sun">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M6 7h12l-1.2 12.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 7Z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M9.5 7V5.5a2.5 2.5 0 0 1 5 0V7" stroke-linecap="round" />
                </svg>
            </div>

            <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tighter">Your bag is empty</h1>
            <p class="mx-auto mt-3 max-w-sm text-pit-700">
                Build a mix first — choose your colours and quantities, and watch them tumble into the
                pit. You will come back here to check out.
            </p>

            <a href="{{ route('order.create') }}" class="btn-primary mt-8">
                Build your mix
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
</x-layout>
