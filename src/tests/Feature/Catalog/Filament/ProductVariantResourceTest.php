<?php

declare(strict_types=1);

use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function createVariantForFilament(Product $product, array $attributes = []): ProductVariant
{
    return app(CreatesProductVariants::class)->handle(
        $product,
        new CreateProductVariantData(
            sku: $attributes['sku'] ?? null,
            label: $attributes['label'] ?? null,
            variantKey: $attributes['variant_key'] ?? null,
            isActive: $attributes['is_active'] ?? true,
        ),
    );
}

function variantRelationManager(Product $product): Testable
{
    return Livewire::test(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the variant relation manager lists product variants', function () {
    $product = Product::factory()->create();
    $second = createVariantForFilament($product, ['sku' => 'FIL-LIST-2', 'label' => 'Second']);

    variantRelationManager($product)
        ->assertCanSeeTableRecords([$product->defaultVariant, $second]);
});

test('a variant can be created through the variant relation manager', function () {
    $product = Product::factory()->create();

    variantRelationManager($product)
        ->callTableAction('create', data: [
            'sku' => 'FIL-NEW-1',
            'label' => 'Large',
            'variant_key' => 'size:large',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $product->id,
        'sku' => 'FIL-NEW-1',
        'label' => 'Large',
        'variant_key' => 'size:large',
        'is_active' => true,
        'position' => 1,
    ]);
});

test('variant creation validation is enforced in the relation manager', function () {
    $product = Product::factory()->create();

    variantRelationManager($product)
        ->callTableAction('create', data: [
            'sku' => $product->defaultVariant->sku,
        ])
        ->assertHasTableActionErrors(['sku']);

    expect($product->variants()->count())->toBe(1);
});

test('a variant can be edited through the variant relation manager', function () {
    $product = Product::factory()->create();
    $variant = createVariantForFilament($product, ['sku' => 'FIL-EDIT-1', 'label' => 'Old label']);

    variantRelationManager($product)
        ->callTableAction('edit', $variant, data: [
            'sku' => 'FIL-EDIT-2',
            'label' => 'New label',
            'variant_key' => 'size:medium',
        ])
        ->assertHasNoTableActionErrors();

    $variant->refresh();

    expect($variant->sku)->toBe('FIL-EDIT-2')
        ->and($variant->label)->toBe('New label')
        ->and($variant->variant_key)->toBe('size:medium')
        ->and($variant->product_id)->toBe($product->id);
});

test('a non-default variant can be deactivated and activated through the relation manager', function () {
    $product = Product::factory()->create();
    $variant = createVariantForFilament($product, ['sku' => 'FIL-LIFE-1']);

    variantRelationManager($product)
        ->callTableAction('deactivate', $variant)
        ->assertHasNoTableActionErrors();

    expect($variant->fresh()->is_active)->toBeFalse();

    variantRelationManager($product)
        ->callTableAction('activate', $variant)
        ->assertHasNoTableActionErrors();

    expect($variant->fresh()->is_active)->toBeTrue();
});

test('the default variant cannot be deactivated through the relation manager', function () {
    $product = Product::factory()->create();

    variantRelationManager($product)
        ->assertTableActionHidden('deactivate', $product->defaultVariant);
});

test('variants can be repositioned through the relation manager', function () {
    $product = Product::factory()->create();
    $first = $product->defaultVariant;
    $second = createVariantForFilament($product, ['sku' => 'FIL-POS-2']);
    $third = createVariantForFilament($product, ['sku' => 'FIL-POS-3']);

    variantRelationManager($product)
        ->call('reorderTable', [$third->id, $first->id, $second->id]);

    expect($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2)
        ->and($third->fresh()->position)->toBe(0)
        ->and($product->variants()->orderBy('position')->pluck('id')->all())->toBe([
            $third->id,
            $first->id,
            $second->id,
        ]);
});

test('non-admin users cannot view the variant relation manager', function () {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create());

    expect(VariantsRelationManager::canViewForRecord($product, EditProduct::class))->toBeFalse();

    $this->actingAs(User::factory()->admin()->create());

    expect(VariantsRelationManager::canViewForRecord($product, EditProduct::class))->toBeTrue();
});

test('admin users can load the product edit page with variants', function () {
    $product = Product::factory()->create();

    $this->get('/admin/products/'.$product->getKey().'/edit')->assertOk();
});
