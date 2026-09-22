<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectMedia
{
    public static function storeImage(UploadedFile $file): array
    {
        $base = 'projects/images/' . Str::uuid()->toString();

        $largePath = $base . '-large.webp';
        $thumbPath = $base . '-thumb.webp';

        Storage::disk('public')->putFileAs('projects/images', $file, basename($largePath));
        Storage::disk('public')->copy($largePath, $thumbPath);

        return [
            'path_large' => $largePath,
            'path_thumb' => $thumbPath,
        ];
    }

    public static function makeThumb(string $largePath): string
    {
        $thumbPath = preg_replace('/(\.[^.\/]+)?$/', '-thumb$1', $largePath, 1);

        if ($thumbPath === $largePath || $thumbPath === null) {
            $thumbPath = $largePath . '-thumb';
        }

        Storage::disk('public')->delete($thumbPath);
        Storage::disk('public')->copy($largePath, $thumbPath);

        return $thumbPath;
    }

    public static function storeFile(UploadedFile $file): string
    {
        $base = 'projects/files/' . Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $path = $base . ($extension ? '.' . $extension : '');

        Storage::disk('public')->putFileAs('projects/files', $file, basename($path));

        return $path;
    }
}
