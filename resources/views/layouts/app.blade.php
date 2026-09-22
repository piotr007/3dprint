<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900">
<div class="min-h-screen">
    <header class="border-b bg-white">
        <div class="mx-auto max-w-6xl px-4 py-4">
            <div class="text-lg font-semibold">3dprint</div>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-8 px-4 py-8">
        <aside class="w-56 shrink-0">
            <nav class="space-y-2 text-sm">
                <a class="block rounded px-3 py-2 {{ request()->is('/') ? 'bg-amber-100 font-semibold' : 'hover:bg-gray-100' }}" href="/">Home</a>
                <a class="block rounded px-3 py-2 {{ request()->is('projects*') ? 'bg-amber-100 font-semibold' : 'hover:bg-gray-100' }}" href="{{ route('projects.index') }}">Projects</a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
