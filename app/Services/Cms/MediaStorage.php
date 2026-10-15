<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Single place that knows where CMS uploads live (public disk, hashed file names). Run `php artisan storage:link`. */
final class MediaStorage
{
    private const DISK = 'public';

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }
}
