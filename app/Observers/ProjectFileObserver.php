<?php

namespace App\Observers;

use App\Models\ProjectFile;
use App\Support\ProjectMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProjectFileObserver
{
    public function creating(ProjectFile $projectFile): void
    {
        if ($projectFile->path instanceof UploadedFile) {
            $projectFile->path = ProjectMedia::storeFile($projectFile->path);
        }
    }

    public function deleting(ProjectFile $projectFile): void
    {
        Storage::disk('public')->delete($projectFile->path);
    }
}
