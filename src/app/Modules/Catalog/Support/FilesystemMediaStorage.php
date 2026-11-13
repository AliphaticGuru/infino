<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Contracts\MediaStorage;
use App\Modules\Catalog\Data\StoredMediaData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class FilesystemMediaStorage implements MediaStorage
{
    public function store(UploadedFile $file, string $disk, string $directory): StoredMediaData
    {
        $mimeType = $file->getMimeType();

        if ($mimeType === null) {
            throw new RuntimeException('The uploaded file has no detectable MIME type.');
        }

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $extension = $this->extensionFromMimeType($mimeType);

        $filename = Str::ulid().'.'.$extension;

        $storedPath = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'public'],
        );

        if ($storedPath === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $metadata = @getimagesize($realPath);

        return new StoredMediaData(
            disk: $disk,
            path: $storedPath,
            originalName: $this->sanitizeOriginalName($file->getClientOriginalName()),
            mimeType: $mimeType,
            size: $file->getSize(),
            width: $metadata === false ? null : $metadata[0],
            height: $metadata === false ? null : $metadata[1],
        );
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    public function url(string $disk, string $path): string
    {
        return Storage::disk($disk)->url($path);
    }

    private function extensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };

    }

    private function sanitizeOriginalName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = basename($name);

        if ($name === '') {
            return null;
        }

        return mb_substr($name, 0, 255);
    }
}
