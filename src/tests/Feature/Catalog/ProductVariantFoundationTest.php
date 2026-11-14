<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('product variants table has the expected schema', function () {
    expect(Schema::hasTable('product_variants'))->toBeTrue()
        ->and(Schema::hasColumns('product_variants', [
            'id',
            'product_id',
            'sku',
            'label',
            'variant_key',
            'is_active',
            'position',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('product variant factory creates a persisted variant', function () {
    $factory = ProductVariant::factory();

    expect($factory)->toBeInstanceOf(ProductVariantFactory::class);

    $variant = $factory->create();

    expect($variant->exists)->toBeTrue()
        ->and($variant->product_id)->toBeInt()
        ->and($variant->product)->toBeInstanceOf(Product::class)
        ->and($variant->sku)->not->toBeEmpty()
        ->and($variant->is_active)->toBeTrue()
        ->and($variant->position)->toBe(0);
});

test('a product has many variants', function () {
    $product = Product::factory()->withoutVariant()->create();

    $variants = ProductVariant::factory()
        ->forProduct($product)
        ->count(2)
        ->create();

    expect($product->variants()->count())->toBe(2)
        ->and($product->variants()->pluck('id')->all())->toBe($variants->pluck('id')->all());
});

test('product factory creates a default variant', function () {
    $product = Product::factory()->create();

    expect($product->variants()->count())->toBe(1)
        ->and($product->variants()->sole()->label)->toBe('Default');
});

test('product factory can create a product without a variant', function () {
    $product = Product::factory()->withoutVariant()->create();

    expect($product->variants()->count())->toBe(0);
});

test('product variant sku must be unique', function () {
    ProductVariant::factory()->create(['sku' => 'UNIQUE-001']);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'UNIQUE-001']))
        ->toThrow(QueryException::class);
});

test('variant key must be unique per product', function () {
    $product = Product::factory()->withoutVariant()->create();

    ProductVariant::factory()
        ->forProduct($product)
        ->create(['variant_key' => 'color:1']);

    expect(fn () => ProductVariant::factory()
        ->forProduct($product)
        ->create(['variant_key' => 'color:1']))
        ->toThrow(QueryException::class);
});

test('multiple null variant keys are allowed', function () {
    $product = Product::factory()->withoutVariant()->create();

    ProductVariant::factory()
        ->forProduct($product)
        ->count(2)
        ->create(['variant_key' => null]);

    expect($product->variants()->count())->toBe(2);
});

test('inactive factory state marks the variant inactive', function () {
    $variant = ProductVariant::factory()->inactive()->create();

    expect($variant->is_active)->toBeFalse();
});

test('deleting a product deletes its variants', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->sole();

    $product->delete();

    $this->assertDatabaseMissing('product_variants', [
        'id' => $variant->id,
    ]);
});
