<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <script>
        // Set the saved or system theme before the page is painted to avoid a flash.
        (() => {
            const theme = localStorage.getItem('theme');
            document.documentElement.classList.toggle(
                'dark',
                theme ? theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches,
            );
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
<div class="min-h-screen">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <div class="text-lg font-semibold">3dprint</div>
            <button
                type="button"
                data-theme-toggle
                class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-gray-700 dark:hover:bg-gray-800"
                aria-label="Dark mode"
                aria-pressed="false"
            >
                <svg class="h-4 w-4 dark:hidden" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
                </svg>
                <svg class="hidden h-4 w-4 dark:block" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"></path>
                </svg>
                <span data-theme-label>Dark mode</span>
            </button>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-8 px-4 py-8">
        <aside class="w-56 shrink-0">
            <nav class="space-y-2 text-sm">
                <a class="block rounded px-3 py-2 {{ request()->is('/') ? 'bg-amber-100 font-semibold dark:bg-amber-900/40 dark:text-amber-200' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}" href="/">Home</a>
                <a class="block rounded px-3 py-2 {{ request()->is('projects*') ? 'bg-amber-100 font-semibold dark:bg-amber-900/40 dark:text-amber-200' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}" href="{{ route('projects.index') }}">Projects</a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
