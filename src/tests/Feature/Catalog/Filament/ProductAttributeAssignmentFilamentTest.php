<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\ProductAttributesRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function productAttributesRelationManager(Product $product): Testable
{
    return Livewire::test(ProductAttributesRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the product attributes relation manager lists assigned attributes', function () {
    $product = Product::factory()->withoutVariant()->create();
    $assigned = ProductAttribute::factory()->create();
    $other = ProductAttribute::factory()->create();
    $product->productAttributes()->attach($assigned);

    productAttributesRelationManager($product)
        ->assertCanSeeTableRecords([$assigned])
        ->assertCanNotSeeTableRecords([$other]);
});

test('attributes can be assigned through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $color = ProductAttribute::factory()->create(['code' => 'color']);
    $size = ProductAttribute::factory()->create(['code' => 'size']);

    productAttributesRelationManager($product)
        ->callTableAction('assignAttributes', data: [
            'attribute_ids' => [$color->id, $size->id],
        ])
        ->assertHasNoTableActionErrors();

    expect($product->productAttributes()->pluck('product_attributes.id')->sort()->values()->all())
        ->toBe([$color->id, $size->id]);
});

test('inactive and descriptive attributes cannot be assigned through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $inactive = ProductAttribute::factory()->inactive()->create(['code' => 'inactive']);
    $descriptive = ProductAttribute::factory()->descriptive()->create(['code' => 'descriptive']);

    productAttributesRelationManager($product)
        ->callTableAction('assignAttributes', data: [
            'attribute_ids' => [$inactive->id],
        ]);

    productAttributesRelationManager($product)
        ->callTableAction('assignAttributes', data: [
            'attribute_ids' => [$descriptive->id],
        ]);

    expect($product->productAttributes()->count())->toBe(0);
});

test('an attribute can be removed through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $attribute = ProductAttribute::factory()->create();
    $product->productAttributes()->attach($attribute);

    productAttributesRelationManager($product)
        ->callTableAction('removeAttribute', $attribute);

    expect($product->productAttributes()->count())->toBe(0);
});

test('an attribute selected by a variant cannot be removed through the relation manager', function () {
    $product = Product::factory()->withoutVariant()->create();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $attribute = ProductAttribute::factory()->create();
    $option = ProductAttributeOption::factory()->forAttribute($attribute)->create();
    $product->productAttributes()->attach($attribute);
    ProductVariantAttributeOption::factory()->forVariant($variant)->forOption($option)->create();

    productAttributesRelationManager($product)
        ->callTableAction('removeAttribute', $attribute);

    expect($product->productAttributes()->count())->toBe(1);
});
