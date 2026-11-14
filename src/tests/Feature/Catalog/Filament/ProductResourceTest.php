<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Products\Pages\CreateProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use App\Modules\Identity\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $this->actingAs(User::factory()->admin()->create());
});

test('the product resource lists products', function () {
    $products = Product::factory()->count(3)->create();

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords($products);
});

test('the product resource can search products by name and slug', function () {
    $matching = Product::factory()->create([
        'name' => 'Wireless Headphones',
        'slug' => 'wireless-headphones',
    ]);
    $other = Product::factory()->create([
        'name' => 'Desk Lamp',
        'slug' => 'desk-lamp',
    ]);

    Livewire::test(ListProducts::class)
        ->searchTable('Wireless')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the product resource can filter products by status', function () {
    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);
    $published = Product::factory()->published()->create();

    Livewire::test(ListProducts::class)
        ->filterTable('status', ProductStatus::Draft->value)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$published]);
});

test('a product can be created through the existing create product contract', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'New Product',
            'description' => 'A new product description.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'New Product',
        'slug' => 'new-product',
        'description' => 'A new product description.',
        'status' => ProductStatus::Draft->value,
        'is_active' => true,
    ]);
});

test('product creation validation is enforced', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    $this->assertDatabaseCount('products', 0);
});

test('a product can be edited through the existing update product contract', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'is_active' => true,
    ]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm([
            'name' => 'New Name',
            'description' => 'Updated description.',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();

    expect($product->name)->toBe('New Name')
        ->and($product->slug)->toBe('new-name')
        ->and($product->description)->toBe('Updated description.')
        ->and($product->is_active)->toBeFalse();
});

test('a draft product can be published through the lifecycle contract', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('publish')
        ->assertNotified();

    expect($product->fresh()->status)->toBe(ProductStatus::Published);
});

test('a published product can be unpublished through the lifecycle contract', function () {
    $product = Product::factory()->published()->create();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('unpublish')
        ->assertNotified();

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('a product can be archived and restored through the lifecycle contract', function () {
    $product = Product::factory()->published()->create();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('archive');

    expect($product->fresh()->status)->toBe(ProductStatus::Archived);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->callAction('restore');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('categories can be assigned to a product through the sync contract', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->mountAction('manageCategories')
        ->set('mountedActions.0.data.category_ids', [$category->id])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($product->categories()->pluck('categories.id')->all())->toBe([$category->id]);
});

test('categories can be removed from a product through the sync contract', function () {
    $product = Product::factory()->create();
    $kept = Category::factory()->create();
    $removed = Category::factory()->create();

    $product->categories()->attach([$kept->id, $removed->id]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->mountAction('manageCategories')
        ->set('mountedActions.0.data.category_ids', [$kept->id])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($product->categories()->pluck('categories.id')->all())->toBe([$kept->id]);
});

test('synchronising categories replaces the existing assignment set', function () {
    $product = Product::factory()->create();
    $removed = Category::factory()->create();
    $added = Category::factory()->create();

    $product->categories()->attach($removed);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->mountAction('manageCategories')
        ->set('mountedActions.0.data.category_ids', [$added->id])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($product->categories()->pluck('categories.id')->all())->toBe([$added->id]);
});

test('admin can load the product resource over http', function () {
    $this->get('/admin/products')->assertOk();
});
