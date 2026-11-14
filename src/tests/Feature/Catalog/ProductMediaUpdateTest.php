<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\UpdatesProductMedia;
use App\Modules\Catalog\Data\UpdateProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;

test('alt text can be updated', function () {
    $media = ProductMedia::factory()->forProduct(Product::factory()->withoutVariant()->create())->create([
        'alt_text' => 'Old text',
    ]);

    $updated = app(UpdatesProductMedia::class)->handle(
        $media,
        new UpdateProductMediaData(altText: 'New text'),
    );

    expect($updated->alt_text)->toBe('New text')
        ->and($media->fresh()->alt_text)->toBe('New text');
});

test('alt text can be cleared with null', function () {
    $media = ProductMedia::factory()->forProduct(Product::factory()->withoutVariant()->create())->create([
        'alt_text' => 'Old text',
    ]);

    app(UpdatesProductMedia::class)->handle(
        $media,
        new UpdateProductMediaData(altText: null),
    );

    expect($media->fresh()->alt_text)->toBeNull();
});

test('alt text is normalized when updated', function () {
    $media = ProductMedia::factory()->forProduct(Product::factory()->withoutVariant()->create())->create();

    app(UpdatesProductMedia::class)->handle(
        $media,
        new UpdateProductMediaData(altText: '  <i>Trimmed</i>  '),
    );

    expect($media->fresh()->alt_text)->toBe('Trimmed');

    app(UpdatesProductMedia::class)->handle(
        $media,
        new UpdateProductMediaData(altText: '   '),
    );

    expect($media->fresh()->alt_text)->toBeNull();
});

test('updating alt text does not change ownership or storage metadata', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $media = ProductMedia::factory()->forVariant($variant)->create([
        'disk' => 'public',
        'path' => 'catalog/products/1/original.png',
        'position' => 4,
        'is_primary' => true,
    ]);

    app(UpdatesProductMedia::class)->handle(
        $media,
        new UpdateProductMediaData(altText: 'Updated'),
    );

    $fresh = $media->fresh();

    expect($fresh->product_id)->toBe($product->id)
        ->and($fresh->product_variant_id)->toBe($variant->id)
        ->and($fresh->disk)->toBe('public')
        ->and($fresh->path)->toBe('catalog/products/1/original.png')
        ->and($fresh->position)->toBe(4)
        ->and($fresh->is_primary)->toBeTrue();
});
