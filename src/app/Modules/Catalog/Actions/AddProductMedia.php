<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\AddsProductMedia;
use App\Modules\Catalog\Contracts\MediaStorage;
use App\Modules\Catalog\Data\AddProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AddProductMedia implements AddsProductMedia
{
    public function __construct(
        private MediaStorage $storage,
    ) {}

    public function handle(Product $product, AddProductMediaData $data): ProductMedia
    {
        $current = Product::query()
            ->whereKey($product->getKey())
            ->firstOrFail();

        $variant = null;

        if ($data->productVariantId !== null) {
            $variant = ProductVariant::query()
                ->whereKey($data->productVariantId)
                ->where('product_id', $current->getKey())
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant does not belong to this product.',
                ]);
            }
        }

        $this->validateUpload($data->file);

        $altText = $this->normalizeAltText($data->altText);

        $disk = (string) config('catalog.media.disk', 'public');
        $directory = (string) config('catalog.media.directory', 'catalog/products').'/'.$current->getKey();

        $allowedDisks = (array) config('catalog.media.allowed_disks', ['public']);

        if (! in_array($disk, $allowedDisks, true)) {
            throw ValidationException::withMessages([
                'disk' => 'The configured media disk is not supported.',
            ]);
        }

        $stored = $this->storage->store($data->file, $disk, $directory);

        try {
            return DB::transaction(function () use ($current, $variant, $stored, $altText, $data): ProductMedia {
                /** @var Collection<int, ProductMedia> $gallery */
                $gallery = $variant !== null
                    ? $variant->media()->lockForUpdate()->get()
                    : $current->media()->lockForUpdate()->get();

                $position = $gallery->isEmpty() ? 0 : (int) $gallery->max('position') + 1;

                // The first image in a gallery is always primary; a new image is
                // promoted only when explicitly requested.
                $isPrimary = $data->isPrimary || $gallery->isEmpty();

                if ($isPrimary) {
                    foreach ($gallery as $existing) {
                        if ($existing->is_primary) {
                            $existing->update(['is_primary' => false]);
                        }
                    }
                }

                $media = ProductMedia::query()->create([
                    'product_id' => $current->getKey(),
                    'product_variant_id' => $variant?->getKey(),
                    'disk' => $stored->disk,
                    'path' => $stored->path,
                    'original_name' => $stored->originalName,
                    'mime_type' => $stored->mimeType,
                    'size' => $stored->size,
                    'width' => $stored->width,
                    'height' => $stored->height,
                    'alt_text' => $altText,
                    'position' => $position,
                    'is_primary' => $isPrimary,
                ]);

                return $media->fresh();
            });
        } catch (\Throwable $e) {
            $this->storage->delete($stored->disk, $stored->path);

            throw $e;
        }
    }

    private function validateUpload(UploadedFile $file): void
    {
        $config = (array) config('catalog.media', []);

        $allowedExtensions = (array) ($config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        $allowedMimeTypes = (array) ($config['allowed_mime_types'] ?? ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        $maxSizeKb = (int) ($config['max_size_kb'] ?? 5120);
        $maxWidth = (int) ($config['max_width'] ?? 8000);
        $maxHeight = (int) ($config['max_height'] ?? 8000);

        $validator = Validator::make(
            [
                'file' => $file,
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'size' => $file->getSize(),
            ],
            [
                'file' => [
                    'required',
                    'file',
                    'image',
                    'mimes:'.implode(',', $allowedExtensions),
                    'max:'.$maxSizeKb,
                    'dimensions:max_width='.$maxWidth.',max_height='.$maxHeight,
                ],
                'mime_type' => [
                    'required',
                    'in:'.implode(',', $allowedMimeTypes),
                ],
                'extension' => [
                    'required',
                    'in:'.implode(',', $allowedExtensions),
                ],
                'size' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:'.($maxSizeKb * 1024),
                ],
            ],
            [
                'file.image' => 'The uploaded file must be a valid image.',
                'file.mimes' => 'The uploaded file must be an image of type: :values.',
                'file.max' => 'The uploaded file may not exceed :max kilobytes.',
                'file.dimensions' => 'The image dimensions are too large.',
                'mime_type.in' => 'The image MIME type is not supported.',
                'extension.in' => 'The image file extension is not supported.',
                'size.max' => 'The image file size is too large.',
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    private function normalizeAltText(?string $altText): ?string
    {
        if ($altText === null) {
            return null;
        }

        $altText = trim(strip_tags($altText));

        if ($altText === '') {
            return null;
        }

        return mb_substr($altText, 0, 255);
    }
}
