<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Factories\ProductAttributeFactory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use App\Modules\Catalog\Support\AttributeType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('product attribute tables have the expected schema', function () {
    expect(Schema::hasTable('product_attributes'))->toBeTrue()
        ->and(Schema::hasColumns('product_attributes', [
            'id', 'code', 'name', 'type', 'is_variant_dimension', 'position', 'is_active', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('product_attribute_options'))->toBeTrue()
        ->and(Schema::hasColumns('product_attribute_options', [
            'id', 'product_attribute_id', 'code', 'label', 'position', 'is_active', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('product_attribute_product'))->toBeTrue()
        ->and(Schema::hasColumns('product_attribute_product', [
            'product_id', 'product_attribute_id',
        ]))->toBeTrue()
        ->and(Schema::hasTable('product_variant_attribute_options'))->toBeTrue()
        ->and(Schema::hasColumns('product_variant_attribute_options', [
            'id', 'product_variant_id', 'product_attribute_id', 'product_attribute_option_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('product attribute factory creates a select variant dimension', function () {
    $factory = ProductAttribute::factory();

    expect($factory)->toBeInstanceOf(ProductAttributeFactory::class);

    $attribute = $factory->create();

    expect($attribute->exists)->toBeTrue()
        ->and($attribute->code)->not->toBeEmpty()
        ->and($attribute->type)->toBe(AttributeType::Select)
        ->and($attribute->is_variant_dimension)->toBeTrue()
        ->and($attribute->is_active)->toBeTrue();
});

test('attribute options are owned by one attribute', function () {
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    expect($option->attribute->is($attribute))->toBeTrue()
        ->and($attribute->options()->count())->toBe(1);
});

test('product assignment and variant selections resolve through relationships', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    $product->productAttributes()->attach($attribute);
    $selection = ProductVariantAttributeOption::factory()
        ->forVariant($variant)
        ->forOption($option)
        ->create();

    expect($product->productAttributes()->pluck('product_attributes.id')->all())->toBe([$attribute->id])
        ->and($variant->attributeOptions()->count())->toBe(1)
        ->and($selection->attribute->is($attribute))->toBeTrue()
        ->and($selection->option->is($option))->toBeTrue();
});

test('an option can never be attached to a different attribute', function () {
    $variant = ProductVariant::factory()->create();
    $attribute = ProductAttribute::factory()->create();
    $otherAttribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($otherAttribute)->create();

    expect(fn () => ProductVariantAttributeOption::factory()
        ->forVariant($variant)
        ->create([
            'product_attribute_id' => $attribute->id,
            'product_attribute_option_id' => $option->id,
        ]))->toThrow(QueryException::class);
});

test('a variant holds at most one option per attribute', function () {
    $variant = ProductVariant::factory()->create();
    $attribute = ProductAttribute::factory()->create();
    $first = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $second = ProductAttributeOption::factory()->forAttribute($attribute)->create();

    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($first)->create();

    expect(fn () => ProductVariantAttributeOption::factory()
        ->forVariant($variant)
        ->forOption($second)
        ->create())->toThrow(QueryException::class);
});
