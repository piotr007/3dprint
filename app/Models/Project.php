<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'short_description',
        'description',
        'print_notes',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class)->orderBy('sort_order');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(ProjectImage::class)->where('is_featured', true);
    }
}
