<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\CreateProductAttribute;
use App\Modules\Catalog\Actions\DeleteProductAttribute;
use App\Modules\Catalog\Actions\FindProductAttribute;
use App\Modules\Catalog\Actions\UpdateProductAttribute;
use App\Modules\Catalog\Contracts\CreatesProductAttributes;
use App\Modules\Catalog\Contracts\DeletesProductAttributes;
use App\Modules\Catalog\Contracts\FindsProductAttributes;
use App\Modules\Catalog\Contracts\UpdatesProductAttributes;
use App\Modules\Catalog\Data\CreateProductAttributeData;
use App\Modules\Catalog\Data\UpdateProductAttributeData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use App\Modules\Catalog\Support\AttributeType;
use Illuminate\Validation\ValidationException;

test('attribute contracts resolve to their actions', function () {
    expect(app(FindsProductAttributes::class))->toBeInstanceOf(FindProductAttribute::class)
        ->and(app(CreatesProductAttributes::class))->toBeInstanceOf(CreateProductAttribute::class)
        ->and(app(UpdatesProductAttributes::class))->toBeInstanceOf(UpdateProductAttribute::class)
        ->and(app(DeletesProductAttributes::class))->toBeInstanceOf(DeleteProductAttribute::class);
});

test('an attribute definition can be created', function () {
    $attribute = app(CreatesProductAttributes::class)->handle(
        new CreateProductAttributeData(
            code: 'color',
            name: 'Color',
            type: AttributeType::Select,
            isVariantDimension: true,
            position: 2,
        ),
    );

    expect($attribute->code)->toBe('color')
        ->and($attribute->name)->toBe('Color')
        ->and($attribute->type)->toBe(AttributeType::Select)
        ->and($attribute->is_variant_dimension)->toBeTrue()
        ->and($attribute->position)->toBe(2)
        ->and($attribute->is_active)->toBeTrue();
});

test('attribute codes are globally unique', function () {
    ProductAttribute::factory()->create(['code' => 'color']);

    expect(fn () => app(CreatesProductAttributes::class)->handle(
        new CreateProductAttributeData(code: 'color', name: 'Other color'),
    ))->toThrow(ValidationException::class);
});

test('attribute codes must be machine safe', function (string $code) {
    expect(fn () => app(CreatesProductAttributes::class)->handle(
        new CreateProductAttributeData(code: $code, name: 'Invalid'),
    ))->toThrow(ValidationException::class);
})->with([
    'uppercase' => ['Color'],
    'space' => ['shoe size'],
    'colon' => ['color:red'],
    'pipe' => ['color|size'],
    'leading dash' => ['-color'],
]);

test('attribute updates change editable fields but never the code', function () {
    $attribute = ProductAttribute::factory()->create([
        'code' => 'color',
        'name' => 'Color',
        'position' => 0,
        'is_active' => true,
    ]);

    $updated = app(UpdatesProductAttributes::class)->handle(
        $attribute,
        new UpdateProductAttributeData(
            name: 'Colour',
            position: 5,
            isActive: false,
        ),
    );

    expect($updated->code)->toBe('color')
        ->and($updated->name)->toBe('Colour')
        ->and($updated->position)->toBe(5)
        ->and($updated->is_active)->toBeFalse();
});

test('an assigned attribute cannot stop being a variant dimension', function () {
    $attribute = ProductAttribute::factory()->create(['is_variant_dimension' => true]);
    $product = Product::factory()->withoutVariant()->create();
    $product->productAttributes()->attach($attribute);

    expect(fn () => app(UpdatesProductAttributes::class)->handle(
        $attribute,
        new UpdateProductAttributeData(isVariantDimension: false),
    ))->toThrow(ValidationException::class);

    expect($attribute->fresh()->is_variant_dimension)->toBeTrue();
});

test('an attribute selected by a variant cannot stop being a variant dimension', function () {
    $attribute = ProductAttribute::factory()->create(['is_variant_dimension' => true]);
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $variant = ProductVariant::factory()->create();
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    expect(fn () => app(UpdatesProductAttributes::class)->handle(
        $attribute,
        new UpdateProductAttributeData(isVariantDimension: false),
    ))->toThrow(ValidationException::class);
});

test('an unreferenced attribute can be deleted with its options', function () {
    $attribute = ProductAttribute::factory()->create();
    ProductAttributeOption::factory()->forAttribute($attribute)->create();

    app(DeletesProductAttributes::class)->handle($attribute);

    $this->assertDatabaseMissing('product_attributes', ['id' => $attribute->id]);
    $this->assertDatabaseMissing('product_attribute_options', ['product_attribute_id' => $attribute->id]);
});

test('an attribute assigned to a product cannot be deleted', function () {
    $attribute = ProductAttribute::factory()->create();
    $product = Product::factory()->withoutVariant()->create();
    $product->productAttributes()->attach($attribute);

    expect(fn () => app(DeletesProductAttributes::class)->handle($attribute))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('product_attributes', ['id' => $attribute->id]);
});

test('an attribute selected by a variant cannot be deleted', function () {
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $variant = ProductVariant::factory()->create();
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    expect(fn () => app(DeletesProductAttributes::class)->handle($attribute))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('product_attributes', ['id' => $attribute->id]);
});

test('attributes can be retrieved and paginated in position order', function () {
    $second = ProductAttribute::factory()->create(['code' => 'size', 'position' => 2]);
    $first = ProductAttribute::factory()->create(['code' => 'color', 'position' => 1]);

    $finder = app(FindsProductAttributes::class);

    expect($finder->find($first->id)->is($first))->toBeTrue()
        ->and($finder->paginate()->pluck('id')->all())->toBe([$first->id, $second->id]);
});
