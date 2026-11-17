@extends('layouts.app')

@section('title', $project->title)

@section('content')
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">{{ $project->title }}</h1>
        <p class="text-gray-700 dark:text-gray-300">{{ $project->short_description }}</p>

        @if ($project->images->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($project->images as $image)
                    <a
                        href="{{ asset('storage/' . $image->path_large) }}"
                        class="block overflow-hidden rounded border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                        data-glightbox="gallery-{{ $project->id }}"
                        data-title="{{ $image->alt }}"
                    >
                        <img
                            src="{{ asset('storage/' . $image->path_thumb) }}"
                            alt="{{ $image->alt }}"
                            class="h-56 w-full object-cover"
                        >
                    </a>
                @endforeach
            </div>
        @endif

        <div class="prose max-w-none text-gray-700 dark:text-gray-300">
            {!! nl2br(e($project->description)) !!}
        </div>

        @if ($project->print_notes)
            <section class="space-y-2">
                <h2 class="text-xl font-semibold">Print notes</h2>
                <div class="prose max-w-none text-gray-700 dark:text-gray-300">
                    {!! nl2br(e($project->print_notes)) !!}
                </div>
            </section>
        @endif

        @if ($project->files->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-xl font-semibold">Downloads</h2>
                <div class="grid gap-3">
                    @foreach ($project->files as $file)
                        <a
                            href="{{ asset('storage/' . $file->path) }}"
                            class="rounded border border-gray-200 bg-white p-4 hover:border-amber-400 dark:border-gray-800 dark:bg-gray-900"
                            download
                        >
                            <div class="font-medium">{{ $file->label }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ strtoupper($file->file_type) }}</div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
