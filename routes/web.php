<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);
Route::get('/projekty', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projekty/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
