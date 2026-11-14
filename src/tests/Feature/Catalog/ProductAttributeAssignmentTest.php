<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\AssignProductAttributes;
use App\Modules\Catalog\Actions\RemoveProductAttributes;
use App\Modules\Catalog\Actions\SyncProductAttributes;
use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Contracts\RemovesProductAttributes;
use App\Modules\Catalog\Contracts\SyncsProductAttributes;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use Illuminate\Validation\ValidationException;

test('product attribute assignment contracts resolve to their actions', function () {
    expect(app(AssignsProductAttributes::class))->toBeInstanceOf(AssignProductAttributes::class)
        ->and(app(RemovesProductAttributes::class))->toBeInstanceOf(RemoveProductAttributes::class)
        ->and(app(SyncsProductAttributes::class))->toBeInstanceOf(SyncProductAttributes::class);
});

test('active variant dimensions can be assigned to a product', function () {
    $product = Product::factory()->withoutVariant()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);

    $result = app(AssignsProductAttributes::class)->handle($product, [$color->id, $size->id]);

    expect($result->productAttributes()->pluck('product_attributes.id')->sort()->values()->all())
        ->toBe([$color->id, $size->id]);
});

test('assigning the same attribute twice is idempotent', function () {
    $product = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->create();

    app(AssignsProductAttributes::class)->handle($product, [$attribute->id]);
    app(AssignsProductAttributes::class)->handle($product, [$attribute->id]);

    expect($product->productAttributes()->count())->toBe(1);
});

test('inactive attributes cannot be assigned', function () {
    $product = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->inactive()->create();

    expect(fn () => app(AssignsProductAttributes::class)->handle($product, [$attribute->id]))
        ->toThrow(ValidationException::class);
});

test('descriptive attributes cannot be assigned in this task', function () {
    $product = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->descriptive()->create();

    expect(fn () => app(AssignsProductAttributes::class)->handle($product, [$attribute->id]))
        ->toThrow(ValidationException::class);
});

test('an attribute can be removed from a product', function () {
    $product = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->create();
    $product->productAttributes()->attach($attribute);

    app(RemovesProductAttributes::class)->handle($product, [$attribute->id]);

    expect($product->productAttributes()->count())->toBe(0);
});

test('an attribute selected by a product variant cannot be removed', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $product->productAttributes()->attach($attribute);
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    expect(fn () => app(RemovesProductAttributes::class)->handle($product, [$attribute->id]))
        ->toThrow(ValidationException::class);

    expect($product->productAttributes()->count())->toBe(1);
});

test('product attributes can be synchronized', function () {
    $product = Product::factory()->withoutVariant()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    $material = ProductAttribute::factory()->create(['code' => 'material']);

    $product->productAttributes()->attach([$color->id, $size->id]);

    app(SyncsProductAttributes::class)->handle($product, [$size->id, $material->id]);

    expect($product->productAttributes()->pluck('product_attributes.id')->sort()->values()->all())
        ->toBe([$size->id, $material->id]);
});

test('synchronization cannot remove an attribute selected by a variant', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    $option = ProductAttributeOption::factory()->forAttribute($color)->create();
    $product->productAttributes()->attach([$color->id, $size->id]);
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    expect(fn () => app(SyncsProductAttributes::class)->handle($product, [$size->id]))
        ->toThrow(ValidationException::class);

    expect($product->productAttributes()->pluck('product_attributes.id')->sort()->values()->all())
        ->toBe([$color->id, $size->id]);
});

test('product attribute assignment is isolated per product', function () {
    $first = Product::factory()->withoutVariant()->create();
    $second = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->create();

    app(AssignsProductAttributes::class)->handle($first, [$attribute->id]);

    expect($first->productAttributes()->count())->toBe(1)
        ->and($second->productAttributes()->count())->toBe(0);
});
