<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\AddsProductMedia;
use App\Modules\Catalog\Data\AddProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
});

function addProductMedia(Product $product, array $attributes = []): ProductMedia
{
    return app(AddsProductMedia::class)->handle(
        $product,
        new AddProductMediaData(
            file: $attributes['file'] ?? mediaUpload(),
            productVariantId: $attributes['product_variant_id'] ?? null,
            altText: $attributes['alt_text'] ?? null,
            isPrimary: $attributes['is_primary'] ?? false,
        ),
    );
}

test('a product gallery image is stored and persisted with metadata', function () {
    $product = Product::factory()->withoutVariant()->create();

    $media = addProductMedia($product, ['alt_text' => 'A red shoe']);

    expect($media->product_id)->toBe($product->id)
        ->and($media->product_variant_id)->toBeNull()
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toStartWith('catalog/products/'.$product->id.'/')
        ->and($media->path)->toEndWith('.png')
        ->and($media->original_name)->toBe('photo.png')
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->width)->toBe(1)
        ->and($media->height)->toBe(1)
        ->and($media->alt_text)->toBe('A red shoe')
        ->and($media->position)->toBe(0)
        ->and($media->is_primary)->toBeTrue();

    Storage::disk('public')->assertExists($media->path);
});

test('a variant gallery image is stored against the variant', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->firstOrFail();

    $media = addProductMedia($product, [
        'product_variant_id' => $variant->id,
        'file' => mediaUpload('variant.jpg'),
    ]);

    expect($media->product_id)->toBe($product->id)
        ->and($media->product_variant_id)->toBe($variant->id)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->path)->toEndWith('.jpg')
        ->and($media->is_primary)->toBeTrue();

    Storage::disk('public')->assertExists($media->path);
});

test('the first image in each gallery becomes primary automatically', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->firstOrFail();

    $productMedia = addProductMedia($product);
    $variantMedia = addProductMedia($product, ['product_variant_id' => $variant->id]);

    expect($productMedia->is_primary)->toBeTrue()
        ->and($variantMedia->is_primary)->toBeTrue();
});

test('an explicit primary clears the previous primary in the same gallery only', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = addProductMedia($product);
    $second = addProductMedia($product);
    $third = addProductMedia($product, ['is_primary' => true]);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeFalse()
        ->and($third->fresh()->is_primary)->toBeTrue()
        ->and($third->fresh()->position)->toBe(2);
});

test('a later image does not become primary unless requested', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = addProductMedia($product);
    $second = addProductMedia($product);

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse();
});

test('alt text is trimmed, stripped of tags and truncated', function () {
    $product = Product::factory()->withoutVariant()->create();

    $media = addProductMedia($product, ['alt_text' => '  <b>Hello</b> world  ']);

    expect($media->alt_text)->toBe('Hello world');

    $blank = addProductMedia($product, ['alt_text' => '   <br>   ']);

    expect($blank->alt_text)->toBeNull();

    $long = addProductMedia($product, ['alt_text' => str_repeat('a', 300)]);

    expect($long->alt_text)->toHaveLength(255);
});

test('an unsupported mime type and extension is rejected', function () {
    $product = Product::factory()->withoutVariant()->create();

    $file = UploadedFile::fake()->createWithContent('notes.txt', 'not an image');

    expect(fn () => addProductMedia($product, ['file' => $file]))
        ->toThrow(ValidationException::class);

    expect(ProductMedia::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('an svg upload is rejected', function () {
    $product = Product::factory()->withoutVariant()->create();

    $file = UploadedFile::fake()->createWithContent(
        'image.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    expect(fn () => addProductMedia($product, ['file' => $file]))
        ->toThrow(ValidationException::class);
});

test('an oversize upload is rejected', function () {
    config(['catalog.media.max_size_kb' => 1]);

    $product = Product::factory()->withoutVariant()->create();

    $file = UploadedFile::fake()->createWithContent(
        'photo.png',
        mediaFixture().str_repeat('0', 2048),
    );

    expect(fn () => addProductMedia($product, ['file' => $file]))
        ->toThrow(ValidationException::class);
});

test('an upload that exceeds the maximum dimensions is rejected', function () {
    config([
        'catalog.media.max_width' => 0,
        'catalog.media.max_height' => 0,
    ]);

    $product = Product::factory()->withoutVariant()->create();

    expect(fn () => addProductMedia($product))
        ->toThrow(ValidationException::class);
});

test('a variant owned by another product is rejected', function () {
    $product = Product::factory()->withoutVariant()->create();
    $otherVariant = Product::factory()->create()->variants()->firstOrFail();

    expect(fn () => addProductMedia($product, ['product_variant_id' => $otherVariant->id]))
        ->toThrow(ValidationException::class);

    expect(ProductMedia::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('an unknown product is rejected', function () {
    $product = Product::factory()->withoutVariant()->create();
    $product->delete();

    expect(fn () => addProductMedia($product))
        ->toThrow(ModelNotFoundException::class);
});

test('a stored file is removed when the database write fails', function () {
    $product = Product::factory()->withoutVariant()->create();

    DB::statement(
        'CREATE TRIGGER product_media_fail_insert BEFORE INSERT ON product_media '.
        "BEGIN SELECT RAISE(ABORT, 'forced insert failure'); END",
    );

    expect(fn () => addProductMedia($product))
        ->toThrow(QueryException::class);

    expect(ProductMedia::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
