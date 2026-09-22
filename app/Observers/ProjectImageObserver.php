<?php

namespace App\Observers;

use App\Models\ProjectImage;
use App\Support\ProjectMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProjectImageObserver
{
    public function creating(ProjectImage $projectImage): void
    {
        if (is_string($projectImage->path_large) && filled($projectImage->path_large) && blank($projectImage->path_thumb)) {
            $projectImage->path_thumb = ProjectMedia::makeThumb($projectImage->path_large);
        }

        if ($projectImage->is_featured) {
            ProjectImage::query()
                ->where('project_id', $projectImage->project_id)
                ->update(['is_featured' => false]);
        }
    }

    public function updating(ProjectImage $projectImage): void
    {
        if ($projectImage->isDirty('path_large') && is_string($projectImage->path_large) && filled($projectImage->path_large)) {
            Storage::disk('public')->delete(array_filter([
                $projectImage->getOriginal('path_large'),
                $projectImage->getOriginal('path_thumb'),
            ]));

            $projectImage->path_thumb = ProjectMedia::makeThumb($projectImage->path_large);
        }

        if ($projectImage->is_featured) {
            ProjectImage::query()
                ->where('project_id', $projectImage->project_id)
                ->whereKeyNot($projectImage->getKey())
                ->update(['is_featured' => false]);
        }
    }

    public function deleting(ProjectImage $projectImage): void
    {
        Storage::disk('public')->delete([
            $projectImage->path_large,
            $projectImage->path_thumb,
        ]);
    }
}
