<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SyncVariantAttributeOptions;
use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Contracts\SyncsVariantAttributeOptions;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Data\SyncVariantAttributeOptionsData;
use App\Modules\Catalog\Data\UpdateProductVariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\VariantKey;
use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<int, ProductAttribute>  $attributes
 */
function attachVariantDimensions(Product $product, array $attributes): void
{
    app(AssignsProductAttributes::class)->handle(
        $product,
        array_map(static fn (ProductAttribute $attribute): int => $attribute->id, $attributes),
    );
}

/**
 * @param  array<int, int>  $attributeOptions
 */
function syncVariantOptions(ProductVariant $variant, array $attributeOptions, ?string $expectedKey = null): ProductVariant
{
    return app(SyncsVariantAttributeOptions::class)->handle(
        $variant,
        new SyncVariantAttributeOptionsData($attributeOptions, $expectedKey),
    );
}

function variantOption(ProductAttribute $attribute, string $code, string $label, int $position = 0): ProductAttributeOption
{
    return ProductAttributeOption::factory()->forAttribute($attribute)->create([
        'code' => $code,
        'label' => $label,
        'position' => $position,
    ]);
}

test('variant attribute option contract resolves to its action', function () {
    expect(app(SyncsVariantAttributeOptions::class))->toBeInstanceOf(SyncVariantAttributeOptions::class);
});

test('variant key derivation is deterministic and ordered by attribute position then code', function () {
    $pairs = [
        ['attributeCode' => 'color', 'optionCode' => 'red', 'attributePosition' => 2],
        ['attributeCode' => 'size', 'optionCode' => 'm', 'attributePosition' => 1],
    ];

    expect(VariantKey::fromPairs($pairs))->toBe('size:m|color:red')
        ->and(VariantKey::fromPairs(array_reverse($pairs)))->toBe('size:m|color:red');
});

test('a variant can select one option per assigned dimension', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color', 'position' => 2]);
    $size = ProductAttribute::factory()->create(['code' => 'size', 'position' => 1]);
    attachVariantDimensions($product, [$color, $size]);

    $red = variantOption($color, 'red', 'Red');
    $medium = variantOption($size, 'm', 'Medium');

    $variant = syncVariantOptions($product->defaultVariant, [
        $color->id => $red->id,
        $size->id => $medium->id,
    ]);

    expect($variant->variant_key)->toBe('size:m|color:red')
        ->and($variant->attributeOptions()->count())->toBe(2)
        ->and($variant->attributeOptions()->pluck('product_attribute_option_id')->sort()->values()->all())
        ->toBe([$red->id, $medium->id]);
});

test('selecting options in a different order derives the same key', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color', 'position' => 1]);
    $size = ProductAttribute::factory()->create(['code' => 'size', 'position' => 2]);
    attachVariantDimensions($product, [$color, $size]);

    $red = variantOption($color, 'red', 'Red');
    $medium = variantOption($size, 'm', 'Medium');

    $variant = syncVariantOptions($product->defaultVariant, [
        $color->id => $red->id,
        $size->id => $medium->id,
    ]);
    $variant = syncVariantOptions($variant, [
        $size->id => $medium->id,
        $color->id => $red->id,
    ]);

    expect($variant->variant_key)->toBe('color:red|size:m');
});

test('a variant created with attribute options derives its key atomically', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');

    $variant = app(CreatesProductVariants::class)->handle(
        $product,
        new CreateProductVariantData(
            sku: 'CONFIG-1',
            label: 'Red',
            attributeOptions: [$color->id => $red->id],
        ),
    );

    expect($variant->variant_key)->toBe('color:red')
        ->and($variant->attributeOptions()->count())->toBe(1)
        ->and($product->fresh()->defaultVariant->is($product->defaultVariant))->toBeTrue();
});

test('a variant can be configured through the update action', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');
    $variant = ProductVariant::factory()->forProduct($product)->create(['sku' => 'CONFIG-2']);

    $updated = app(UpdatesProductVariants::class)->handle(
        $variant,
        new UpdateProductVariantData(attributeOptions: [$color->id => $red->id]),
    );

    expect($updated->variant_key)->toBe('color:red')
        ->and($updated->attributeOptions()->count())->toBe(1)
        ->and($updated->sku)->toBe('CONFIG-2');
});

test('incomplete selections are rejected and rolled back', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    attachVariantDimensions($product, [$color, $size]);
    $red = variantOption($color, 'red', 'Red');
    $medium = variantOption($size, 'm', 'Medium');

    expect(fn () => syncVariantOptions($product->defaultVariant, [$color->id => $red->id]))
        ->toThrow(ValidationException::class);

    expect($product->defaultVariant->attributeOptions()->count())->toBe(0)
        ->and($product->defaultVariant->fresh()->variant_key)->toBeNull();

    $configured = syncVariantOptions($product->defaultVariant, [
        $color->id => $red->id,
        $size->id => $medium->id,
    ]);

    expect($configured->attributeOptions()->count())->toBe(2);
});

test('an option that belongs to another attribute is rejected', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    attachVariantDimensions($product, [$color, $size]);
    $medium = variantOption($size, 'm', 'Medium');

    expect(fn () => syncVariantOptions($product->defaultVariant, [
        $color->id => $medium->id,
        $size->id => $medium->id,
    ]))->toThrow(ValidationException::class);

    expect($product->defaultVariant->attributeOptions()->count())->toBe(0);
});

test('an inactive option cannot be newly selected', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');
    $red->update(['is_active' => false]);

    expect(fn () => syncVariantOptions($product->defaultVariant, [$color->id => $red->id]))
        ->toThrow(ValidationException::class);
});

test('an inactive attribute cannot be newly selected', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');
    $color->update(['is_active' => false]);

    expect(fn () => syncVariantOptions($product->defaultVariant, [$color->id => $red->id]))
        ->toThrow(ValidationException::class);
});

test('an existing selection may remain after its option and attribute are deactivated', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');

    $variant = syncVariantOptions($product->defaultVariant, [$color->id => $red->id]);

    $color->update(['is_active' => false]);
    $red->update(['is_active' => false]);

    $variant = syncVariantOptions($variant, [$color->id => $red->id]);

    expect($variant->variant_key)->toBe('color:red')
        ->and($variant->attributeOptions()->count())->toBe(1);
});

test('an attribute that is not assigned to the product is rejected', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);
    attachVariantDimensions($product, [$color]);
    attachVariantDimensions($other, [$size]);
    $medium = variantOption($size, 'm', 'Medium');

    expect(fn () => syncVariantOptions($product->defaultVariant, [$size->id => $medium->id]))
        ->toThrow(ValidationException::class);
});

test('duplicate option combinations are rejected per product', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');

    $first = ProductVariant::factory()->forProduct($product)->create();
    syncVariantOptions($first, [$color->id => $red->id]);

    $second = ProductVariant::factory()->forProduct($product)->create();

    expect(fn () => syncVariantOptions($second, [$color->id => $red->id]))
        ->toThrow(ValidationException::class);

    expect($second->attributeOptions()->count())->toBe(0)
        ->and($second->fresh()->variant_key)->toBeNull();
});

test('a manually supplied key must match the derived key', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');

    expect(fn () => syncVariantOptions(
        $product->defaultVariant,
        [$color->id => $red->id],
        'manual:key',
    ))->toThrow(ValidationException::class);

    $variant = syncVariantOptions(
        $product->defaultVariant,
        [$color->id => $red->id],
        'color:red',
    );

    expect($variant->variant_key)->toBe('color:red');
});

test('a variant without normalized selections keeps a manual key', function () {
    $product = Product::factory()->create();

    $variant = app(CreatesProductVariants::class)->handle(
        $product,
        new CreateProductVariantData(sku: 'LEGACY-1', variantKey: 'legacy:key'),
    );

    expect($variant->variant_key)->toBe('legacy:key')
        ->and($variant->attributeOptions()->count())->toBe(0);
});

test('clearing selections on an undimensioned product clears the derived key', function () {
    $product = Product::factory()->create();

    $variant = syncVariantOptions($product->defaultVariant, []);

    expect($variant->variant_key)->toBeNull()
        ->and($variant->attributeOptions()->count())->toBe(0);
});

test('a failed selection change rolls back the whole variant update', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');
    $blue = variantOption($color, 'blue', 'Blue');
    $variant = ProductVariant::factory()->forProduct($product)->create(['sku' => 'ROLLBACK-1']);
    syncVariantOptions($variant, [$color->id => $red->id]);

    expect(fn () => app(UpdatesProductVariants::class)->handle(
        $variant,
        new UpdateProductVariantData(
            sku: 'ROLLBACK-2',
            attributeOptions: [$color->id => $blue->id],
            variantKey: 'manual:key',
        ),
    ))->toThrow(ValidationException::class);

    $variant->refresh();

    expect($variant->sku)->toBe('ROLLBACK-1')
        ->and($variant->variant_key)->toBe('color:red')
        ->and($variant->attributeOptions()->sole()->product_attribute_option_id)->toBe($red->id);
});

test('configuring variant options does not touch inventory or stock movements', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    attachVariantDimensions($product, [$color]);
    $red = variantOption($color, 'red', 'Red');
    $variant = $product->defaultVariant;

    $inventory = Inventory::factory()->create([
        'variant_id' => $variant->id,
        'quantity' => 10,
    ]);

    app(AdjustsStock::class)->handle(
        $inventory,
        new AdjustStockData(quantityDelta: 5, reason: 'Stock received'),
    );

    $movementsBefore = StockMovement::query()->count();

    syncVariantOptions($variant, [$color->id => $red->id]);

    expect($inventory->fresh()->quantity)->toBe(15)
        ->and(StockMovement::query()->count())->toBe($movementsBefore)
        ->and($variant->fresh()->attributeOptions()->count())->toBe(1);
});
