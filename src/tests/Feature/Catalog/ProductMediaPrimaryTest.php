<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\DeletesProductMedia;
use App\Modules\Catalog\Contracts\SetsPrimaryProductMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;

test('setting a primary inside the product gallery leaves other galleries untouched', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);
    $variantPrimary = ProductMedia::factory()->forVariant($variant)->create(['position' => 0, 'is_primary' => true]);

    app(SetsPrimaryProductMedia::class)->handle($second);

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($variantPrimary->fresh()->is_primary)->toBeTrue();
});

test('setting a primary inside a variant gallery leaves other galleries untouched', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $otherVariant = ProductVariant::factory()->forProduct($product)->create();

    $productPrimary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $first = ProductMedia::factory()->forVariant($variant)->create(['position' => 0, 'is_primary' => true]);
    $second = ProductMedia::factory()->forVariant($variant)->create(['position' => 1]);
    $otherPrimary = ProductMedia::factory()->forVariant($otherVariant)->create(['position' => 0, 'is_primary' => true]);

    app(SetsPrimaryProductMedia::class)->handle($second);

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($productPrimary->fresh()->is_primary)->toBeTrue()
        ->and($otherPrimary->fresh()->is_primary)->toBeTrue();
});

test('setting the primary is idempotent', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    app(SetsPrimaryProductMedia::class)->handle($first);
    app(SetsPrimaryProductMedia::class)->handle($first);

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse();
});

test('setting a primary on one product does not affect another product', function () {
    $productA = Product::factory()->withoutVariant()->create();
    $productB = Product::factory()->withoutVariant()->create();

    $aPrimary = ProductMedia::factory()->forProduct($productA)->create(['position' => 0, 'is_primary' => true]);
    $bMedia = ProductMedia::factory()->forProduct($productB)->create(['position' => 0, 'is_primary' => true]);

    app(SetsPrimaryProductMedia::class)->handle($bMedia);

    expect($aPrimary->fresh()->is_primary)->toBeTrue()
        ->and($bMedia->fresh()->is_primary)->toBeTrue();
});

test('deleting the primary promotes the next image by position then id', function () {
    $product = Product::factory()->withoutVariant()->create();

    $primary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $samePosition = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $later = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    app(DeletesProductMedia::class)->handle($primary);

    expect($samePosition->fresh()->is_primary)->toBeTrue()
        ->and($later->fresh()->is_primary)->toBeFalse();
});

test('deleting the only image leaves the gallery without a primary', function () {
    $product = Product::factory()->withoutVariant()->create();

    $primary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);

    app(DeletesProductMedia::class)->handle($primary);

    expect(ProductMedia::query()->count())->toBe(0);
});

test('deleting a non-primary image does not change the primary', function () {
    $product = Product::factory()->withoutVariant()->create();

    $primary = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $other = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    app(DeletesProductMedia::class)->handle($other);

    expect($primary->fresh()->is_primary)->toBeTrue();
});
