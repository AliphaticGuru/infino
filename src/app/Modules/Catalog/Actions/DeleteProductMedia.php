<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\DeletesProductMedia;
use App\Modules\Catalog\Contracts\MediaStorage;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeleteProductMedia implements DeletesProductMedia
{
    public function __construct(
        private MediaStorage $storage,
    ) {}

    public function handle(ProductMedia $media): void
    {
        DB::transaction(function () use ($media): void {
            $current = ProductMedia::query()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $disk = $current->disk;
            $path = $current->path;
            $wasPrimary = $current->is_primary;

            $current->delete();

            if ($wasPrimary) {
                $this->promoteNextPrimary($current);
            }
        });

        // File deletion happens after commit so a rollback does not remove a
        // still-referenced file. A failed file delete is logged but does not
        // fail the request.
        try {
            $this->storage->delete($media->disk, $media->path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete product media file after row deletion.', [
                'disk' => $media->disk,
                'path' => $media->path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Promote the next image in the same gallery when the deleted row was
     * primary.
     */
    private function promoteNextPrimary(ProductMedia $deleted): void
    {
        $gallery = $deleted->product_variant_id === null
            ? $deleted->product->media()->orderBy('position')->orderBy('id')->get()
            : $deleted->productVariant->media()->orderBy('position')->orderBy('id')->get();

        if ($gallery->isNotEmpty()) {
            $next = $gallery->first();

            $next->update(['is_primary' => true]);
        }
    }
}
