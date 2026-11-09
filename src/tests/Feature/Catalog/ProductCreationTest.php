<?php

use App\Foundation\Modules\ModuleRepository;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('products table has the expected schema', function () {
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasColumns('products', [
            'id',
            'name',
            'slug',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('catalog module is discovered and registered', function () {
    $modules = app(ModuleRepository::class)->all();

    expect($modules->firstWhere('name', 'Catalog'))->not->toBeNull()
        ->and(app()->getProviders(CatalogServiceProvider::class))->not->toBeEmpty()
        ->and(app(CreatesProducts::class))->toBeInstanceOf(CreateProduct::class);
});

test('product factory creates a persisted product', function () {
    $factory = Product::factory();

    expect($factory)->toBeInstanceOf(ProductFactory::class);

    $product = $factory->create();

    expect($product->exists)->toBeTrue()
        ->and($product->name)->not->toBeEmpty()
        ->and($product->slug)->not->toBeEmpty()
        ->and($product->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'slug' => $product->slug,
    ]);
});

test('create product action persists a product', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(
            name: 'Wireless Headphones',
            description: 'Noise cancelling over-ear headphones.',
        ),
    );

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->toBe('Wireless Headphones')
        ->and($product->slug)->toBe('wireless-headphones')
        ->and($product->description)->toBe('Noise cancelling over-ear headphones.')
        ->and($product->is_active)->toBeTrue();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'slug' => 'wireless-headphones',
    ]);
});

test('create product action accepts a missing description', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: 'Wireless Headphones'),
    );

    expect($product->description)->toBeNull();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'description' => null,
    ]);
});

test('create product action generates unique slugs for duplicate names', function () {
    $action = app(CreatesProducts::class);
    $data = new CreateProductData(
        name: 'Wireless Headphones',
        description: 'Noise cancelling over-ear headphones.',
    );

    $firstProduct = $action->handle($data);
    $secondProduct = $action->handle($data);
    $thirdProduct = $action->handle($data);

    expect($firstProduct->slug)->toBe('wireless-headphones')
        ->and($secondProduct->slug)->toBe('wireless-headphones-2')
        ->and($thirdProduct->slug)->toBe('wireless-headphones-3');

    $this->assertDatabaseCount('products', 3);
});

test('create product action falls back to a default slug when the name has no slug characters', function () {
    $product = app(CreatesProducts::class)->handle(
        new CreateProductData(name: '!!!'),
    );

    expect($product->slug)->toBe('product');
});

test('create product action requires a name', function () {
    $action = app(CreatesProducts::class);

    expect(fn () => $action->handle(new CreateProductData(name: '')))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 0);
});

test('create product action rejects names longer than 255 characters', function () {
    $action = app(CreatesProducts::class);

    expect(fn () => $action->handle(
        new CreateProductData(name: str_repeat('a', 256)),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('products', 0);
});
