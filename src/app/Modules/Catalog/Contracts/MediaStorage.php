<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\StoredMediaData;
use Illuminate\Http\UploadedFile;

interface MediaStorage
{
    /**
     * Store an uploaded file on the given disk and directory.
     */
    public function store(UploadedFile $file, string $disk, string $directory): StoredMediaData;

    /**
     * Delete the stored file at the given path on the given disk.
     */
    public function delete(string $disk, string $path): void;

    /**
     * Check whether a file exists at the given path on the given disk.
     */
    public function exists(string $disk, string $path): bool;

    /**
     * Get the public URL for a stored file, or null if it cannot be served.
     */
    public function url(string $disk, string $path): ?string;
}
