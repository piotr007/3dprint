<?php

namespace App\Providers;

use App\Models\ProjectFile;
use App\Models\ProjectImage;
use App\Observers\ProjectFileObserver;
use App\Observers\ProjectImageObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProjectImage::observe(ProjectImageObserver::class);
        ProjectFile::observe(ProjectFileObserver::class);
    }
}
