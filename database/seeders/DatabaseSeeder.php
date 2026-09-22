<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@3dprint.local',
        ], [
            'name' => 'Admin',
            'password' => 'password',
        ]);

        User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
        ]);

        Project::updateOrCreate([
            'slug' => 'sample-bracket',
        ], [
            'title' => 'Sample Bracket',
            'short_description' => 'A simple bracket for testing the project layout and gallery.',
            'description' => 'This sample project demonstrates the MVP project page, gallery, and download area.',
            'print_notes' => 'PLA, 0.2 mm layer height, 20% infill.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
