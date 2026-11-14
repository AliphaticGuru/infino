<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\DeletesProductMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function storedProductMedia(Product $product, array $attributes = []): ProductMedia
{
    $path = 'catalog/products/'.$product->id.'/'.fake()->uuid().'.png';

    Storage::disk('public')->put($path, mediaFixture());

    return ProductMedia::factory()->forProduct($product)->create(array_merge([
        'disk' => 'public',
        'path' => $path,
        'position' => 0,
        'is_primary' => true,
    ], $attributes));
}

test('deleting media removes the row and the stored file', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = storedProductMedia($product);

    Storage::disk('public')->assertExists($media->path);

    app(DeletesProductMedia::class)->handle($media);

    expect(ProductMedia::query()->whereKey($media->id)->exists())->toBeFalse();

    Storage::disk('public')->assertMissing($media->path);
});

test('deleting a primary image promotes the next image in the same gallery', function () {
    $product = Product::factory()->withoutVariant()->create();

    $primary = storedProductMedia($product, ['position' => 0, 'is_primary' => true]);
    $next = storedProductMedia($product, ['position' => 1, 'is_primary' => false]);

    app(DeletesProductMedia::class)->handle($primary);

    expect($next->fresh()->is_primary)->toBeTrue();
});

test('deleting media does not fail when the stored file is already missing', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = storedProductMedia($product);

    Storage::disk('public')->delete($media->path);

    app(DeletesProductMedia::class)->handle($media);

    expect(ProductMedia::query()->whereKey($media->id)->exists())->toBeFalse();
});

test('deleting a missing media row throws a model not found exception', function () {
    $product = Product::factory()->withoutVariant()->create();
    $media = ProductMedia::factory()->forProduct($product)->create();

    ProductMedia::query()->whereKey($media->id)->delete();

    expect(fn () => app(DeletesProductMedia::class)->handle($media))
        ->toThrow(ModelNotFoundException::class);
});
