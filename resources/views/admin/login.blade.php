<x-admin-layout title="Sign in">
    <div class="mx-auto w-full max-w-sm px-4">
        <h1 class="text-2xl font-bold tracking-tight">Catalogue admin</h1>
        <p class="mt-2 text-sm text-pit-600">Sign in to manage ball types and photos.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4 rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-pit-800">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                    value="{{ old('email') }}"
                    class="mt-1.5 block w-full rounded-lg border border-pit-300 px-3 py-2.5 shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-pit-800">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="mt-1.5 block w-full rounded-lg border border-pit-300 px-3 py-2.5 shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30">
            </div>

            <label class="flex items-center gap-2 text-sm text-pit-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-pit-300 text-pit-600 focus:ring-pit-500/30">
                Remember me
            </label>

            @error('email')
                <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" class="w-full rounded-xl bg-pit-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-pit-500">
            No account yet? Run <code class="rounded bg-pit-100 px-1 py-0.5 font-mono">php artisan ballpit:make-admin you@example.com</code>
        </p>
    </div>
</x-admin-layout>
