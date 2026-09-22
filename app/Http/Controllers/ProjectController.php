<?php

namespace App\Http\Controllers;

use App\Models\Project;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::query()
            ->where('is_published', true)
            ->with(['featuredImage', 'images'])
            ->latest('published_at')
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        abort_unless($project->is_published, 404);

        $project->load(['images', 'files']);

        return view('projects.show', compact('project'));
    }
}
