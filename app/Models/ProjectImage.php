<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use App\Support\ProjectMedia;

class ProjectImage extends Model
{
    protected $fillable = [
        'project_id',
        'path_large',
        'path_thumb',
        'alt',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function setPathLargeAttribute(mixed $value): void
    {
        if ($value instanceof UploadedFile) {
            $paths = ProjectMedia::storeImage($value);
            $this->attributes['path_large'] = $paths['path_large'];
            $this->attributes['path_thumb'] = $paths['path_thumb'];

            return;
        }

        $this->attributes['path_large'] = $value;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
