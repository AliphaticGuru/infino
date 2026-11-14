<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function variantOptionsRelationManager(Product $product): Testable
{
    return Livewire::test(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

function assignVariantDimension(Product $product, ProductAttribute $attribute): void
{
    app(AssignsProductAttributes::class)->handle($product, [$attribute->id]);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('a variant can be created with attribute options through the relation manager', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    assignVariantDimension($product, $color);
    $red = ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'red', 'label' => 'Red']);

    variantOptionsRelationManager($product)
        ->callTableAction('create', data: [
            'sku' => 'FIL-ATTR-1',
            'label' => 'Red',
            'attribute_options' => [$color->id => $red->id],
        ])
        ->assertHasNoTableActionErrors();

    $variant = ProductVariant::query()->where('sku', 'FIL-ATTR-1')->sole();

    expect($variant->variant_key)->toBe('color:red')
        ->and($variant->attributeOptions()->count())->toBe(1);

    $this->assertDatabaseHas('product_variant_attribute_options', [
        'product_variant_id' => $variant->id,
        'product_attribute_id' => $color->id,
        'product_attribute_option_id' => $red->id,
    ]);
});

test('a variant can be reconfigured through the relation manager', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    assignVariantDimension($product, $color);
    $red = ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'red', 'label' => 'Red']);
    $blue = ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'blue', 'label' => 'Blue']);
    $variant = ProductVariant::factory()->forProduct($product)->create(['sku' => 'FIL-ATTR-2']);

    variantOptionsRelationManager($product)
        ->callTableAction('edit', $variant, data: [
            'sku' => 'FIL-ATTR-2',
            'attribute_options' => [$color->id => $red->id],
        ])
        ->assertHasNoTableActionErrors();

    variantOptionsRelationManager($product)
        ->callTableAction('edit', $variant, data: [
            'sku' => 'FIL-ATTR-2',
            'attribute_options' => [$color->id => $blue->id],
        ])
        ->assertHasNoTableActionErrors();

    $variant->refresh();

    expect($variant->variant_key)->toBe('color:blue')
        ->and($variant->attributeOptions()->sole()->product_attribute_option_id)->toBe($blue->id);
});

test('a configured variant cannot be created without a complete selection', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    assignVariantDimension($product, $color);
    ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'red', 'label' => 'Red']);

    variantOptionsRelationManager($product)
        ->callTableAction('create', data: [
            'sku' => 'FIL-ATTR-3',
        ])
        ->assertHasTableActionErrors(['attribute_options.'.$color->id]);

    $this->assertDatabaseMissing('product_variants', ['sku' => 'FIL-ATTR-3']);
});

test('editing a legacy variant on a dimensioned product requires options', function () {
    $product = Product::factory()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    assignVariantDimension($product, $color);
    $red = ProductAttributeOption::factory()->forAttribute($color)->create(['code' => 'red', 'label' => 'Red']);
    $variant = $product->defaultVariant;

    variantOptionsRelationManager($product)
        ->callTableAction('edit', $variant, data: [
            'sku' => 'FIL-LEGACY-1',
        ])
        ->assertHasTableActionErrors(['attribute_options.'.$color->id]);

    expect($variant->fresh()->sku)->not->toBe('FIL-LEGACY-1');

    variantOptionsRelationManager($product)
        ->callTableAction('edit', $variant, data: [
            'sku' => 'FIL-LEGACY-1',
            'attribute_options' => [$color->id => $red->id],
        ])
        ->assertHasNoTableActionErrors();

    expect($variant->fresh()->sku)->toBe('FIL-LEGACY-1')
        ->and($variant->fresh()->variant_key)->toBe('color:red');
});

test('the variant form keeps the manual key for products without dimensions', function () {
    $product = Product::factory()->create();

    variantOptionsRelationManager($product)
        ->callTableAction('create', data: [
            'sku' => 'FIL-MANUAL-1',
            'variant_key' => 'legacy:key',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'FIL-MANUAL-1',
        'variant_key' => 'legacy:key',
    ]);
});
