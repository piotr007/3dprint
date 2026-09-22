@extends('layouts.app')

@section('title', 'Projects')

@section('content')
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">Projects</h1>

        <div class="grid gap-4">
            @forelse ($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="rounded border bg-white p-4 hover:border-amber-400">
                    @if ($project->featuredImage)
                        <img src="{{ asset('storage/' . $project->featuredImage->path_thumb) }}" alt="{{ $project->title }}" class="mb-3 h-40 w-full rounded object-cover">
                    @elseif ($project->images->isNotEmpty())
                        <img src="{{ asset('storage/' . $project->images->first()->path_thumb) }}" alt="{{ $project->title }}" class="mb-3 h-40 w-full rounded object-cover">
                    @endif
                    <div class="text-lg font-semibold">{{ $project->title }}</div>
                    <div class="mt-1 text-sm text-gray-600">{{ $project->short_description }}</div>
                </a>
            @empty
                <div class="rounded border bg-white p-4 text-gray-600">No published projects yet.</div>
            @endforelse
        </div>
    </div>
@endsection
