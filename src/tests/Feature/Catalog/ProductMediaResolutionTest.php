<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;

test('the product primary image is returned when set', function () {
    $product = Product::factory()->withoutVariant()->create();

    ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => false]);
    $primary = ProductMedia::factory()->forProduct($product)->create(['position' => 1, 'is_primary' => true]);

    expect($product->primaryMedia()?->id)->toBe($primary->id);
});

test('the product primary image falls back to the first image by position', function () {
    $product = Product::factory()->withoutVariant()->create();

    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1, 'is_primary' => false]);
    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => false]);

    expect($product->primaryMedia()?->id)->toBe($first->id);
});

test('the product primary image is null when the gallery is empty', function () {
    $product = Product::factory()->withoutVariant()->create();

    expect($product->primaryMedia())->toBeNull();
});

test('a variant resolves its own primary image first', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productPrimary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $variantPrimary = ProductMedia::factory()->forVariant($variant)->create(['position' => 0, 'is_primary' => true]);

    expect($variant->resolvedPrimaryMedia()?->id)->toBe($variantPrimary->id)
        ->and($variant->resolvedPrimaryMedia()?->id)->not->toBe($productPrimary->id);
});

test('a variant falls back to the product primary image', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productPrimary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);

    expect($variant->resolvedPrimaryMedia()?->id)->toBe($productPrimary->id);
});

test('a variant falls back to the first product image when no product primary exists', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => false]);

    expect($variant->resolvedPrimaryMedia()?->id)->toBe($first->id);
});

test('a variant falls back to the product when its gallery has no primary', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productPrimary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    ProductMedia::factory()->forVariant($variant)->create(['position' => 0, 'is_primary' => false]);

    expect($variant->resolvedPrimaryMedia()?->id)->toBe($productPrimary->id);
});

test('a variant resolved primary image is null when no media exists', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    expect($variant->resolvedPrimaryMedia())->toBeNull();
});

test('the variant gallery falls back to the product gallery when empty', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    expect($variant->gallery()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('the variant gallery is used when it has images', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $variantMedia = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);

    expect($variant->gallery()->pluck('id')->all())->toBe([$variantMedia->id]);
});
