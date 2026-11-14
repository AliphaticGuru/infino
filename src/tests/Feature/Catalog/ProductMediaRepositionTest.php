<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\RepositionsProductMedia;
use App\Modules\Catalog\Data\RepositionProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

function repositionMedia(Product $product, ?int $variantId, array $mediaIds): Collection
{
    return app(RepositionsProductMedia::class)->handle(
        $product,
        new RepositionProductMediaData(
            productVariantId: $variantId,
            mediaIds: $mediaIds,
        ),
    );
}

test('a complete product gallery can be reordered and positions are normalized', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0, 'is_primary' => true]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);
    $third = ProductMedia::factory()->forProduct($product)->create(['position' => 2]);

    $result = repositionMedia($product, null, [$third->id, $first->id, $second->id]);

    expect($result->pluck('id')->all())->toBe([$third->id, $first->id, $second->id])
        ->and($third->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2)
        ->and($first->fresh()->is_primary)->toBeTrue();
});

test('a complete variant gallery can be reordered', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $first = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);
    $second = ProductMedia::factory()->forVariant($variant)->create(['position' => 1]);

    repositionMedia($product, $variant->id, [$second->id, $first->id]);

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1);
});

test('reordering rejects duplicate ids', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $second = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    expect(fn () => repositionMedia($product, null, [$first->id, $first->id]))
        ->toThrow(ValidationException::class);

    expect($first->fresh()->position)->toBe(0)
        ->and($second->fresh()->position)->toBe(1);
});

test('reordering rejects a missing id', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    ProductMedia::factory()->forProduct($product)->create(['position' => 1]);

    expect(fn () => repositionMedia($product, null, [$first->id]))
        ->toThrow(ValidationException::class);
});

test('reordering rejects an unknown id', function () {
    $product = Product::factory()->withoutVariant()->create();

    $first = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);

    expect(fn () => repositionMedia($product, null, [$first->id, 999999]))
        ->toThrow(ValidationException::class);
});

test('reordering rejects an id from another gallery', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productMedia = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $variantMedia = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);

    expect(fn () => repositionMedia($product, null, [$productMedia->id, $variantMedia->id]))
        ->toThrow(ValidationException::class);
});

test('reordering one gallery leaves the other gallery untouched', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $productFirst = ProductMedia::factory()->forProduct($product)->create(['position' => 0]);
    $productSecond = ProductMedia::factory()->forProduct($product)->create(['position' => 1]);
    $variantFirst = ProductMedia::factory()->forVariant($variant)->create(['position' => 0]);
    $variantSecond = ProductMedia::factory()->forVariant($variant)->create(['position' => 1]);

    repositionMedia($product, null, [$productSecond->id, $productFirst->id]);

    expect($variantFirst->fresh()->position)->toBe(0)
        ->and($variantSecond->fresh()->position)->toBe(1);
});

test('reordering rejects a variant owned by another product', function () {
    $product = Product::factory()->withoutVariant()->create();
    $otherVariant = Product::factory()->create()->variants()->firstOrFail();

    ProductMedia::factory()->forVariant($otherVariant)->create(['position' => 0]);

    expect(fn () => repositionMedia($product, $otherVariant->id, []))
        ->toThrow(ValidationException::class);
});
