<x-admin-layout title="Sign in">
    <div class="mx-auto w-full max-w-sm px-4">
        <h1 class="font-display text-3xl font-extrabold tracking-tighter">Catalogue admin</h1>
        <p class="mt-2 text-sm text-pit-600">Sign in to manage ball types and photos.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="card mt-6 space-y-4 p-6">
            @csrf

            <div>
                <label for="email" class="label-strong">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                    value="{{ old('email') }}"
                    class="field mt-1.5">
            </div>

            <div>
                <label for="password" class="label-strong">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="field mt-1.5">
            </div>

            <label class="flex items-center gap-2 text-sm text-pit-700">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-2 border-pit-950 text-berry focus:ring-2 focus:ring-sun">
                Remember me
            </label>

            @error('email')
                <p role="alert" class="error-text">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn-primary w-full">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-pit-500">
            No account yet? Run <code class="rounded bg-pit-200 px-1 py-0.5 font-mono">php artisan ballpit:make-admin you@example.com</code>
        </p>
    </div>
</x-admin-layout>
